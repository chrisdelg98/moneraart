<?php

declare(strict_types=1);

use App\Actions\Downloads\IssueDownloadGrants;
use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\RefundOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\Facades\Settings;
use App\Support\Money;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        // Distinct ids, as PayPal returns: the unique index on
        // provider_refund_id is what stops the same refund being recorded twice.
        '*/refund' => Http::sequence()
            ->push(['id' => 'REF-123', 'status' => 'COMPLETED'])
            ->push(['id' => 'REF-124', 'status' => 'COMPLETED'])
            ->whenEmpty(Http::response(['id' => 'REF-125', 'status' => 'COMPLETED'])),
    ]);
});

/** A delivered order with a captured payment, ready to refund. */
function deliveredOrder(int $cents = 599): Order
{
    $order = paidOrder($cents);

    Payment::create([
        'order_id' => $order->getKey(),
        'provider' => 'paypal',
        'provider_capture_id' => '3C679366HH908993F',
        'status' => PaymentStatus::Completed,
        'amount_cents' => $cents,
        'currency' => 'USD',
    ]);

    app(CompleteOrder::class)($order);

    return $order->refresh();
}

it('refunds in full and takes the files back', function (): void {
    // A refund that leaves the customer downloading is a refund that cost
    // twice.
    $order = deliveredOrder(599);

    expect(DownloadGrant::whereNull('revoked_at')->count())->toBe(1);

    app(RefundOrder::class)($order, 'Duplicate purchase.');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->refunded_at)->not->toBeNull()
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(DownloadGrant::whereNull('revoked_at')->count())->toBe(0)
        ->and(DownloadGrant::first()->revoke_reason)->toBe('Order refunded');
});

it('leaves access in place on a partial refund', function (): void {
    // They are still entitled to what they kept paying for.
    $order = deliveredOrder(1000);

    app(RefundOrder::class)($order, 'Goodwill.', Money::fromCents(300));

    expect($order->fresh()->status)->not->toBe(OrderStatus::Refunded)
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and(DownloadGrant::whereNull('revoked_at')->count())->toBe(1);
});

it('revokes access once partial refunds add up to the whole payment', function (): void {
    $order = deliveredOrder(1000);

    app(RefundOrder::class)($order, 'First half.', Money::fromCents(500));
    app(RefundOrder::class)($order->fresh(), 'Second half.', Money::fromCents(500));

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(DownloadGrant::whereNull('revoked_at')->count())->toBe(0);
});

it('refuses to refund more than was paid', function (): void {
    app(RefundOrder::class)(deliveredOrder(599), 'Too much.', Money::fromCents(9999));
})->throws(RuntimeException::class, 'more than was paid');

it('refuses an order with no captured payment', function (): void {
    app(RefundOrder::class)(paidOrder(), 'Nothing to refund.');
})->throws(RuntimeException::class, 'no captured payment');

it('refuses to refund twice', function (): void {
    $order = deliveredOrder(599);
    app(RefundOrder::class)($order, 'First.');

    app(RefundOrder::class)($order->fresh(), 'Again.');
})->throws(RuntimeException::class, 'cannot be refunded');

it('records who refunded and why', function (): void {
    app(RefundOrder::class)(deliveredOrder(599), 'Files would not open.');

    $log = AuditLog::where('action', 'order.refunded')->firstOrFail();

    expect($log->metadata['reason'])->toBe('Files would not open.')
        ->and($log->metadata['full'])->toBeTrue()
        ->and(Refund::first()->provider_refund_id)->toBe('REF-123');
});

it('applies a refund issued inside PayPal, not just one started here', function (): void {
    // A refund from PayPal's own dashboard still has to take access back.
    $order = deliveredOrder(599);

    app(RefundOrder::class)->fromWebhook($order, [
        'id' => 'REF-FROM-PAYPAL',
        'amount' => ['currency_code' => 'USD', 'value' => '5.99'],
    ]);

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(DownloadGrant::whereNull('revoked_at')->count())->toBe(0)
        ->and(Refund::first()->reason)->toBe('Refunded in PayPal');
});

it('ignores a refund event it has already applied', function (): void {
    $order = deliveredOrder(599);
    $resource = ['id' => 'REF-X', 'amount' => ['currency_code' => 'USD', 'value' => '5.99']];

    app(RefundOrder::class)->fromWebhook($order, $resource);
    app(RefundOrder::class)->fromWebhook($order->fresh(), $resource);

    expect(Refund::count())->toBe(1);
});

it('stops a revoked link from downloading', function (): void {
    $order = deliveredOrder(599);
    $grant = DownloadGrant::firstOrFail();
    $token = app(IssueDownloadGrants::class)->newToken();
    $grant->forceFill(['token_hash' => hash('sha256', $token)])->save();

    $this->get(route('download', $token))->assertOk();

    app(RefundOrder::class)($order, 'Refunded.');

    $this->get(route('download', $token))->assertStatus(410);
});
