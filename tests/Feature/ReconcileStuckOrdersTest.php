<?php

declare(strict_types=1);

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\MarkOrderPaid;
use App\Enums\OrderStatus;
use App\Jobs\ProcessPayPalWebhook;
use App\Jobs\ReconcilePendingWebhooks;
use App\Jobs\ReconcileStuckOrders;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\PayPal\PayPalOrderService;
use App\Support\Facades\Settings;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);

    Settings::setMany([
        'paypal.mode' => 'sandbox',
        'paypal.sandbox.client_id' => 'id',
        'paypal.sandbox.client_secret' => 'secret',
    ]);
});

/** A pending order of the given age, optionally knowing its PayPal order. */
function stuckOrder(int $minutesOld = 60, bool $withPayPal = true): Order
{
    $order = paidOrder(599);
    $order->forceFill([
        'status' => OrderStatus::Pending,
        'paid_at' => null,
        'metadata' => $withPayPal ? ['paypal_order_id' => '5O190127TN364715T'] : [],
    ])->save();

    Order::where('id', $order->id)->update(['created_at' => now()->subMinutes($minutesOld)]);

    return $order->refresh();
}

/** What PayPal reports when asked about the order. */
function fakeRetrieve(string $status, ?string $capturedValue = null): void
{
    $body = ['id' => '5O190127TN364715T', 'status' => $status, 'payer' => ['email_address' => 'buyer@example.com']];

    if ($capturedValue !== null) {
        $body['purchase_units'] = [['payments' => ['captures' => [[
            'id' => '3C679366HH908993F',
            'status' => 'COMPLETED',
            'amount' => ['currency_code' => 'USD', 'value' => $capturedValue],
        ]]]]];
    }

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        '*/v2/checkout/orders/*' => Http::response($body),
    ]);
}

it('recovers an order PayPal already captured', function (): void {
    // The path that stops a customer paying and receiving nothing.
    $order = stuckOrder();
    fakeRetrieve('COMPLETED', '5.99');

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(Payment::count())->toBe(1)
        ->and(DownloadGrant::count())->toBe(1);
});

it('still verifies the amount when recovering', function (): void {
    $order = stuckOrder();
    fakeRetrieve('COMPLETED', '0.01');

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::ManualReview)
        ->and(Payment::count())->toBe(0);
});

it('never cancels an order it could not ask PayPal about', function (): void {
    // A paid order cancelled by a timeout is the worst outcome this system can
    // produce, so unreachable is not treated as unpaid.
    $order = stuckOrder(minutesOld: 60 * 48);

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        '*/v2/checkout/orders/*' => Http::response([], 503),
    ]);

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('flags an approved but uncaptured order for a human', function (): void {
    // The money is reserved and the customer is waiting, but capturing
    // automatically could charge someone who walked away.
    $order = stuckOrder();
    fakeRetrieve('APPROVED');

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::ManualReview)
        ->and($order->fresh()->manual_review_reason)->toContain('approved but never captured');
});

it('leaves a recent order alone', function (): void {
    // A customer still on the PayPal popup is not stuck.
    $order = stuckOrder(minutesOld: 5);
    fakeRetrieve('CREATED');

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('cancels a day-old order PayPal says was never paid', function (): void {
    $order = stuckOrder(minutesOld: 60 * 25);
    fakeRetrieve('CREATED');

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('cancels a day-old order that never reached PayPal', function (): void {
    $order = stuckOrder(minutesOld: 60 * 25, withPayPal: false);

    app(ReconcileStuckOrders::class)->handle(
        app(PayPalOrderService::class),
        app(MarkOrderPaid::class),
        app(CompleteOrder::class),
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->manual_review_reason)->toContain('before reaching PayPal');
});

it('re-dispatches a verified webhook whose job never ran', function (): void {
    Queue::fake();

    // The row is written before the job is queued, so a worker that crashed
    // between the two leaves a payment event nobody acted on.
    $event = WebhookEvent::create([
        'provider' => 'paypal', 'event_id' => 'WH-ORPHAN',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'signature_verified' => true,
        'payload' => ['id' => 'WH-ORPHAN'], 'status' => 'received',
        'received_at' => now()->subMinutes(10),
    ]);

    app(ReconcilePendingWebhooks::class)->handle();

    Queue::assertPushed(ProcessPayPalWebhook::class, 1);
    expect($event->fresh()->status)->toBe('received');
});

it('leaves a webhook that just arrived to its own job', function (): void {
    Queue::fake();

    WebhookEvent::create([
        'provider' => 'paypal', 'event_id' => 'WH-FRESH',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'signature_verified' => true,
        'payload' => ['id' => 'WH-FRESH'], 'status' => 'received',
        'received_at' => now(),
    ]);

    app(ReconcilePendingWebhooks::class)->handle();

    Queue::assertNothingPushed();
});
