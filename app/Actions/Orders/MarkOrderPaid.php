<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\OrderConfirmed;
use App\Support\BlindIndex;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a verified PayPal capture into a paid order.
 *
 * Both the capture response and the webhook call this, and both will run for a
 * normal purchase. They converge safely: the order row is locked first and the
 * action returns early if the order is already paid, and `provider_capture_id`
 * carries a unique index. Double fulfilment is impossible at two independent
 * layers. See §6.8.
 */
final class MarkOrderPaid
{
    /** @param array<string, mixed> $capture the PayPal capture resource */
    public function __invoke(Order $order, array $capture, string $source = 'capture'): PaymentOutcome
    {
        $outcome = $this->process($order, $capture, $source);

        // Queued outside the transaction on purpose: a job enqueued inside one
        // can be picked up by a worker before the commit lands, or survive a
        // rollback and reference a row that no longer says what it said.
        //
        // Both outcomes send it. A held order is the case that needs it most —
        // the money has left the customer's account and no other email will
        // reach them until a human releases the files.
        if (in_array($outcome->result, ['paid', 'manual_review'], true)) {
            $outcome->order->customer->notify(new OrderConfirmed($outcome->order));
        }

        return $outcome;
    }

    /** @param array<string, mixed> $capture */
    private function process(Order $order, array $capture, string $source): PaymentOutcome
    {
        return DB::transaction(function () use ($order, $capture, $source): PaymentOutcome {
            // Lock before reading status, or two concurrent paths both see
            // "pending" and both fulfil.
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($order->status->isFulfillable()) {
                return PaymentOutcome::alreadyHandled($order);
            }

            $captureId = (string) ($capture['id'] ?? '');
            $status = (string) ($capture['status'] ?? '');

            if ($captureId === '') {
                return $this->flag($order, 'PayPal returned a capture with no id.');
            }

            // ── The check that matters ────────────────────────────────────
            // An attacker who tampers with a client-side amount lands here,
            // in manual review, not on a download page. See §6.5.
            $amount = $capture['amount'] ?? [];
            $currency = (string) ($amount['currency_code'] ?? '');
            $value = (string) ($amount['value'] ?? '0');

            if ($currency !== $order->currency) {
                return $this->flag($order, "PayPal captured {$currency}; the order is in {$order->currency}.");
            }

            $captured = Money::fromDecimal($value, $currency);

            if ($captured->cents !== $order->total_cents) {
                return $this->flag($order, sprintf(
                    'PayPal captured %s; the order total is %s.',
                    $captured->toDecimalString(),
                    $order->total()->toDecimalString(),
                ));
            }

            // A PENDING capture is a PayPal risk hold, not a payment.
            if ($status === 'PENDING') {
                return $this->flag($order, 'PayPal is holding this payment for review.');
            }

            if ($status !== 'COMPLETED') {
                return $this->fail($order, "PayPal reported the capture as {$status}.");
            }

            $payment = $this->recordPayment($order, $capture, $captureId, $captured);

            if ($payment === null) {
                // The unique index caught a replay the lock did not.
                return PaymentOutcome::alreadyHandled($order);
            }

            $order->status = OrderStatus::Paid;
            $order->paid_at = now();
            $order->save();

            Log::channel(config('logging.default'))->info('order.paid', [
                'order' => $order->number,
                'source' => $source,
            ]);

            return PaymentOutcome::paid($order, $payment);
        });
    }

    /** @param array<string, mixed> $capture */
    private function recordPayment(Order $order, array $capture, string $captureId, Money $amount): ?Payment
    {
        $breakdown = $capture['seller_receivable_breakdown'] ?? [];

        try {
            return Payment::create([
                'order_id' => $order->getKey(),
                'provider' => 'paypal',
                'mode' => (string) config('paypal.mode', 'sandbox'),
                'provider_order_id' => $order->metadata['paypal_order_id'] ?? null,
                'provider_capture_id' => $captureId,
                'status' => PaymentStatus::Completed,
                'amount_cents' => $amount->cents,
                'currency' => $amount->currency,
                'fee_cents' => $this->centsOrNull($breakdown['paypal_fee'] ?? null),
                'net_cents' => $this->centsOrNull($breakdown['net_amount'] ?? null),
                'payer_email_hash' => $this->payerEmailHash($capture),
                'payer_id' => $capture['payer_id'] ?? null,
                'raw_response' => $capture,
                'verified_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** @param array<string, mixed>|null $money */
    private function centsOrNull(?array $money): ?int
    {
        $value = $money['value'] ?? null;

        return is_string($value)
            ? Money::fromDecimal($value, (string) ($money['currency_code'] ?? 'USD'))->cents
            : null;
    }

    /** @param array<string, mixed> $capture */
    private function payerEmailHash(array $capture): ?string
    {
        $email = $capture['payer']['email_address'] ?? null;

        return is_string($email) ? BlindIndex::email($email) : null;
    }

    private function flag(Order $order, string $reason): PaymentOutcome
    {
        $order->status = OrderStatus::ManualReview;
        $order->manual_review_reason = $reason;
        $order->save();

        Log::channel(config('logging.default'))->warning('order.manual_review', [
            'order' => $order->number,
            'reason' => $reason,
        ]);

        return PaymentOutcome::needsReview($order, $reason);
    }

    private function fail(Order $order, string $reason): PaymentOutcome
    {
        $order->status = OrderStatus::Failed;
        $order->manual_review_reason = $reason;
        $order->save();

        return PaymentOutcome::failed($order, $reason);
    }
}
