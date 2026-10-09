<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Audit\AuditLogger;
use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Refunds a payment and takes the files back with it.
 *
 * Revoking the download grants is the part that matters: a refund that leaves
 * the customer able to keep downloading is a refund that cost twice. The same
 * path runs whether the refund started here or in PayPal's own dashboard, so a
 * refund issued outside this admin still removes access. See §6.9.
 */
final class RefundOrder
{
    public function __construct(
        private readonly PayPalClient $client,
        private readonly AuditLogger $audit,
    ) {}

    /** @param Money|null $amount null refunds the full payment */
    public function __invoke(Order $order, string $reason, ?Money $amount = null): Refund
    {
        $payment = $order->payment;

        if ($payment === null || $payment->provider_capture_id === null) {
            throw new RuntimeException('There is no captured payment to refund.');
        }

        if (! $order->status->canTransitionTo(OrderStatus::Refunded)) {
            throw new RuntimeException("An order that is {$order->status->value} cannot be refunded.");
        }

        $amount ??= $payment->amount();

        if ($amount->cents > $payment->amount_cents) {
            throw new RuntimeException('That is more than was paid.');
        }

        $response = $this->client->request(
            'POST',
            "/v2/payments/captures/{$payment->provider_capture_id}/refund",
            [
                'amount' => [
                    'currency_code' => $amount->currency,
                    'value' => $amount->toDecimalString(),
                ],
                'note_to_payer' => mb_substr($reason, 0, 255),
            ],
            // Deterministic, so a retry after a timeout returns the original
            // refund instead of issuing a second one.
            ['PayPal-Request-Id' => 'refund-'.$order->uuid.'-'.$amount->cents],
        );

        return $this->record($order, $payment, $amount, $reason, $response);
    }

    /**
     * Applies a refund PayPal already processed — from its dashboard, or from
     * a dispute resolved against us.
     *
     * @param  array<string, mixed>  $resource  the PayPal refund resource
     */
    public function fromWebhook(Order $order, array $resource): ?Refund
    {
        $payment = $order->payment;
        $refundId = (string) ($resource['id'] ?? '');

        if ($payment === null || $refundId === '' || Refund::where('provider_refund_id', $refundId)->exists()) {
            return null;
        }

        $amount = Money::fromDecimal(
            (string) data_get($resource, 'amount.value', '0'),
            (string) data_get($resource, 'amount.currency_code', $order->currency),
        );

        return $this->record($order, $payment, $amount, 'Refunded in PayPal', $resource);
    }

    /** @param array<string, mixed> $response */
    private function record(Order $order, Payment $payment, Money $amount, string $reason, array $response): Refund
    {
        return DB::transaction(function () use ($order, $payment, $amount, $reason, $response): Refund {
            $refund = Refund::create([
                'payment_id' => $payment->getKey(),
                'provider_refund_id' => (string) ($response['id'] ?? 'unknown-'.$order->uuid),
                'amount_cents' => $amount->cents,
                'reason' => $reason,
                'status' => (string) ($response['status'] ?? 'COMPLETED'),
                'initiated_by_user_id' => auth()->id(),
                'raw_response' => $response,
            ]);

            $refundedTotal = (int) $payment->refunds()->sum('amount_cents');
            $isFull = $refundedTotal >= $payment->amount_cents;

            $payment->forceFill([
                'status' => $isFull ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ])->save();

            // A partial refund leaves the customer entitled to what they kept
            // paying for, so access only goes on a full one.
            if ($isFull) {
                $order->forceFill([
                    'status' => OrderStatus::Refunded,
                    'refunded_at' => now(),
                ])->save();

                DownloadGrant::where('order_id', $order->getKey())
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now(),
                        'revoked_by_user_id' => auth()->id(),
                        'revoke_reason' => 'Order refunded',
                    ]);
            }

            $order->customer->recalculateOrderTotals();

            $this->audit->record('order.refunded', $order, [
                'amount' => $amount->toDecimalString(),
                'full' => $isFull,
                'reason' => $reason,
            ]);

            Log::channel(config('logging.default'))->info('order.refunded', [
                'order' => $order->number,
                'amount' => $amount->toDecimalString(),
                'full' => $isFull,
            ]);

            return $refund;
        });
    }
}
