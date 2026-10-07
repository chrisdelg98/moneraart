<?php

declare(strict_types=1);

use App\Actions\Orders\MarkOrderPaid;
use App\Enums\OrderStatus;
use App\Models\Payment;
use App\Support\BlindIndex;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->markPaid = app(MarkOrderPaid::class);
});

/** A PayPal capture resource. */
function capture(string $value = '14.38', string $status = 'COMPLETED', string $currency = 'USD'): array
{
    return [
        'id' => '3C679366HH908993F',
        'status' => $status,
        'amount' => ['currency_code' => $currency, 'value' => $value],
        'payer' => ['email_address' => 'buyer@example.com'],
        'seller_receivable_breakdown' => [
            'paypal_fee' => ['currency_code' => 'USD', 'value' => '0.84'],
            'net_amount' => ['currency_code' => 'USD', 'value' => '13.54'],
        ],
    ];
}

it('marks an order paid when the capture matches', function (): void {
    $order = pendingOrder(1438);

    $outcome = ($this->markPaid)($order, capture('14.38'));

    expect($outcome->result)->toBe('paid')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and(Payment::count())->toBe(1);
});

it('records the fee and net so the books are not guesswork', function (): void {
    $payment = ($this->markPaid)(pendingOrder(1438), capture('14.38'))->payment;

    expect($payment->fee_cents)->toBe(84)
        ->and($payment->net_cents)->toBe(1354)
        ->and($payment->verified_at)->not->toBeNull();
});

it('sends an underpaid capture to manual review, never to fulfilment', function (): void {
    // This is the check that stops a tampered client-side amount.
    $order = pendingOrder(1438);

    $outcome = ($this->markPaid)($order, capture('1.00'));

    expect($outcome->result)->toBe('manual_review')
        ->and($order->fresh()->status)->toBe(OrderStatus::ManualReview)
        ->and($order->fresh()->manual_review_reason)->toContain('1.00')
        ->and(Payment::count())->toBe(0);
});

it('sends an overpaid capture to manual review too', function (): void {
    $order = pendingOrder(1438);

    expect(($this->markPaid)($order, capture('99.00'))->result)->toBe('manual_review')
        ->and($order->fresh()->status)->toBe(OrderStatus::ManualReview);
});

it('refuses a capture in the wrong currency', function (): void {
    $order = pendingOrder(1438);

    $outcome = ($this->markPaid)($order, capture('14.38', currency: 'EUR'));

    expect($outcome->result)->toBe('manual_review')
        ->and($order->fresh()->manual_review_reason)->toContain('EUR');
});

it('treats a PENDING capture as a hold, not a payment', function (): void {
    $order = pendingOrder(1438);

    $outcome = ($this->markPaid)($order, capture('14.38', status: 'PENDING'));

    expect($outcome->result)->toBe('manual_review')
        ->and($order->fresh()->status)->toBe(OrderStatus::ManualReview)
        ->and(Payment::count())->toBe(0);
});

it('fails an order on a declined capture', function (): void {
    $order = pendingOrder(1438);

    $outcome = ($this->markPaid)($order, capture('14.38', status: 'DECLINED'));

    expect($outcome->result)->toBe('failed')
        ->and($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and(Payment::count())->toBe(0);
});

it('creates exactly one payment when capture and webhook both arrive', function (): void {
    $order = pendingOrder(1438);

    $first = ($this->markPaid)($order, capture('14.38'), 'capture');
    $second = ($this->markPaid)($order->fresh(), capture('14.38'), 'webhook');

    expect($first->result)->toBe('paid')
        ->and($second->result)->toBe('already_handled')
        ->and($second->isSuccessful())->toBeTrue()
        ->and(Payment::count())->toBe(1);
});

it('refuses a second payment row even if the order status says otherwise', function (): void {
    // The unique index is the guarantee the lock is not. See §6.7.
    $order = pendingOrder(1438);
    ($this->markPaid)($order, capture('14.38'));

    expect(fn () => Payment::create([
        'order_id' => $order->getKey(),
        'provider' => 'paypal',
        'provider_capture_id' => '3C679366HH908993F',
        'status' => 'completed',
        'amount_cents' => 1438,
        'currency' => 'USD',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('hashes the payer email rather than storing it', function (): void {
    $payment = ($this->markPaid)(pendingOrder(1438), capture('14.38'))->payment;

    expect($payment->payer_email_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($payment->payer_email_hash)->toBe(BlindIndex::email('buyer@example.com'));
});

it('rejects a capture with no id', function (): void {
    $order = pendingOrder(1438);
    $bad = capture('14.38');
    unset($bad['id']);

    expect(($this->markPaid)($order, $bad)->result)->toBe('manual_review');
});

it('does not re-pay an order that is already completed', function (): void {
    $order = pendingOrder(1438);
    $order->update(['status' => OrderStatus::Completed]);

    expect(($this->markPaid)($order, capture('14.38'))->result)->toBe('already_handled')
        ->and(Payment::count())->toBe(0);
});
