<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\MarkOrderPaid;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Coupons\CouponLedger;
use App\Services\PayPal\PayPalOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recovers orders where both the capture call and the webhook failed.
 *
 * This is the path that stops a customer paying and receiving nothing. The
 * governing rule: **never cancel an order without asking PayPal first.** A paid
 * order cancelled by a timeout is the worst outcome this system can produce.
 *
 * See §7.5.
 */
class ReconcileStuckOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Long enough that a customer still on the PayPal popup is left alone. */
    private const STUCK_AFTER_MINUTES = 30;

    /** Past this, an order with no payment at PayPal is abandoned, not pending. */
    private const ABANDON_AFTER_HOURS = 24;

    public function handle(
        PayPalOrderService $paypal,
        MarkOrderPaid $markPaid,
        CompleteOrder $complete,
    ): void {
        $orders = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $this->reconcile($order, $paypal, $markPaid, $complete);
        }
    }

    private function reconcile(
        Order $order,
        PayPalOrderService $paypal,
        MarkOrderPaid $markPaid,
        CompleteOrder $complete,
    ): void {
        $paypalOrderId = $order->metadata['paypal_order_id'] ?? null;

        // No PayPal order means checkout never got that far. Nothing to ask
        // about, so age alone decides.
        if (! is_string($paypalOrderId) || $paypalOrderId === '') {
            $this->abandonIfOld($order);

            return;
        }

        try {
            $remote = $paypal->retrieve($paypalOrderId);
        } catch (Throwable $e) {
            // Unreachable is not the same as unpaid. Leave it for the next run.
            Log::channel(config('logging.default'))->warning('reconcile.unreachable', [
                'order' => $order->number,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $status = (string) ($remote['status'] ?? '');
        $capture = data_get($remote, 'purchase_units.0.payments.captures.0');

        if (is_array($capture)) {
            $capture['payer'] ??= $remote['payer'] ?? null;

            $outcome = $markPaid($order, $capture, 'reconcile');

            if ($outcome->isSuccessful() && $outcome->order->status->canTransitionTo(OrderStatus::Completed)) {
                $complete($outcome->order);
            }

            Log::channel(config('logging.default'))->info('reconcile.recovered', [
                'order' => $order->number,
                'result' => $outcome->result,
            ]);

            return;
        }

        // Approved but never captured: the money is reserved and the customer
        // is waiting. A human decides, because capturing automatically here
        // could charge someone who walked away.
        if ($status === 'APPROVED') {
            $order->forceFill([
                'status' => OrderStatus::ManualReview,
                'manual_review_reason' => 'PayPal shows this as approved but never captured.',
            ])->save();

            return;
        }

        $this->abandonIfOld($order, $status);
    }

    private function abandonIfOld(Order $order, ?string $remoteStatus = null): void
    {
        if ($order->created_at->isAfter(now()->subHours(self::ABANDON_AFTER_HOURS))) {
            return;
        }

        DB::transaction(function () use ($order, $remoteStatus): void {
            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'manual_review_reason' => $remoteStatus !== null
                    ? "Abandoned at checkout; PayPal reported {$remoteStatus}."
                    : 'Abandoned before reaching PayPal.',
            ])->save();

            // The use this order was holding goes back to the pool. Without
            // this, a limited coupon is slowly consumed by carts nobody paid.
            app(CouponLedger::class)->release($order);
        });
    }
}
