<?php

declare(strict_types=1);

use App\Actions\Orders\MarkOrderPaid;
use App\Enums\OrderStatus;
use App\Jobs\ProcessPayPalWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Support\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    Settings::setMany([
        'paypal.mode' => 'sandbox',
        'paypal.sandbox.client_id' => 'test-client',
        'paypal.sandbox.client_secret' => 'test-secret',
        'paypal.sandbox.webhook_id' => 'WH-TEST-ID',
    ]);
});

/** A PAYMENT.CAPTURE.COMPLETED event for the given order. */
function webhookPayload(Order $order, string $eventId = 'WH-1', string $value = '14.38'): array
{
    return [
        'id' => $eventId,
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource_type' => 'capture',
        'resource' => [
            'id' => '3C679366HH908993F',
            'status' => 'COMPLETED',
            'custom_id' => $order->uuid,
            'amount' => ['currency_code' => 'USD', 'value' => $value],
        ],
    ];
}

/**
 * Fakes PayPal's API rather than the service, so the real verification path
 * runs — including the cert_url host guard.
 */
function stubVerification(bool $verdict): void
{
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        '*/verify-webhook-signature' => Http::response([
            'verification_status' => $verdict ? 'SUCCESS' : 'FAILURE',
        ]),
    ]);
}

/** The transmission headers PayPal sends alongside every event. */
function signatureHeaders(array $overrides = []): array
{
    return array_merge([
        'paypal-transmission-id' => 'tx-1',
        'paypal-transmission-time' => '2026-10-07T15:00:00Z',
        'paypal-cert-url' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-1',
        'paypal-auth-algo' => 'SHA256withRSA',
        'paypal-transmission-sig' => 'signature',
    ], $overrides);
}

it('rejects an event whose signature does not verify', function (): void {
    stubVerification(false);
    $order = pendingOrder();

    // 403 and not 200: PayPal retries non-2xx, so a 200 here would permanently
    // discard real payment events while the webhook id is briefly wrong.
    $this->postJson(route('webhooks.paypal'), webhookPayload($order), signatureHeaders())->assertForbidden();

    expect(WebhookEvent::count())->toBe(0);
});

it('rejects an event with no id', function (): void {
    stubVerification(true);

    $this->postJson(route('webhooks.paypal'), ['event_type' => 'X'], signatureHeaders())->assertStatus(400);
});

it('records a verified event and queues it', function (): void {
    Queue::fake();
    stubVerification(true);
    $order = pendingOrder();

    $this->postJson(route('webhooks.paypal'), webhookPayload($order), signatureHeaders())->assertOk();

    expect(WebhookEvent::count())->toBe(1)
        ->and(WebhookEvent::first()->signature_verified)->toBeTrue();

    Queue::assertPushed(ProcessPayPalWebhook::class);
});

it('acknowledges a replayed event without recording it twice', function (): void {
    Queue::fake();
    stubVerification(true);
    $order = pendingOrder();
    $payload = webhookPayload($order);

    $this->postJson(route('webhooks.paypal'), $payload, signatureHeaders())->assertOk();
    $this->postJson(route('webhooks.paypal'), $payload, signatureHeaders())->assertOk();
    $this->postJson(route('webhooks.paypal'), $payload, signatureHeaders())->assertOk();

    // The unique index on event_id is the lock.
    expect(WebhookEvent::count())->toBe(1);
    Queue::assertPushed(ProcessPayPalWebhook::class, 1);
});

it('marks the order paid when the job runs', function (): void {
    stubVerification(true);
    $order = pendingOrder(1438);

    $this->postJson(route('webhooks.paypal'), webhookPayload($order), signatureHeaders())->assertOk();

    // The webhook path delivers too — this is what rescues the customer who
    // closed the tab before the capture call returned.
    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(Payment::count())->toBe(1)
        ->and(WebhookEvent::first()->status)->toBe('processed');
});

it('creates one payment when the capture path already paid the order', function (): void {
    stubVerification(true);
    $order = pendingOrder(1438);

    app(MarkOrderPaid::class)($order, webhookPayload($order)['resource'], 'capture');
    $this->postJson(route('webhooks.paypal'), webhookPayload($order), signatureHeaders())->assertOk();

    expect(Payment::count())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);
});

it('applies the same amount verification as the capture path', function (): void {
    stubVerification(true);
    $order = pendingOrder(1438);

    $this->postJson(route('webhooks.paypal'), webhookPayload($order, value: '0.01'), signatureHeaders())->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::ManualReview)
        ->and(Payment::count())->toBe(0);
});

it('ignores an event for an order it cannot find', function (): void {
    stubVerification(true);
    $order = pendingOrder();
    $payload = webhookPayload($order);
    $payload['resource']['custom_id'] = 'not-a-real-uuid';

    $this->postJson(route('webhooks.paypal'), $payload, signatureHeaders())->assertOk();

    expect(WebhookEvent::first()->status)->toBe('ignored')
        ->and(Payment::count())->toBe(0);
});

it('records but does not act on an event type it does not handle', function (): void {
    stubVerification(true);
    $order = pendingOrder();
    $payload = webhookPayload($order, 'WH-2');
    $payload['event_type'] = 'CUSTOMER.DISPUTE.CREATED';

    $this->postJson(route('webhooks.paypal'), $payload, signatureHeaders())->assertOk();

    expect(WebhookEvent::first()->status)->toBe('ignored');
});

it('needs no csrf token, since PayPal has none to send', function (): void {
    stubVerification(true);

    $this->postJson(route('webhooks.paypal'), webhookPayload(pendingOrder()), signatureHeaders())
        ->assertOk();
});

it('refuses a cert url that is not from PayPal', function (): void {
    // An attacker controlling cert_url could otherwise point certificate
    // fetching wherever they like. See §6.7.
    stubVerification(true);

    $this->postJson(
        route('webhooks.paypal'),
        webhookPayload(pendingOrder()),
        signatureHeaders(['paypal-cert-url' => 'https://evil.example.com/certs/CERT-1']),
    )->assertForbidden();

    expect(WebhookEvent::count())->toBe(0);
});
