<?php

declare(strict_types=1);

namespace App\Services\Coupons;

use App\Models\Coupon;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * Consuming and releasing a coupon's uses.
 *
 * A usage row is written the moment an order claims the discount, not when the
 * order is paid. Between those two moments the customer is at PayPal, and a
 * limit that only counts completed sales can be overrun by however many carts
 * are in flight. Cancelling a pending order gives the use back.
 *
 * Call reserve() inside the transaction that writes the order. See §14.2.
 */
final class CouponLedger
{
    /**
     * @throws RuntimeException when the last use went to someone else first
     */
    public function reserve(Coupon $coupon, Order $order, Money $discount): void
    {
        // The validator already checked the limit; this is the check that is
        // true under concurrency, because the row is locked when it runs.
        $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->getKey());

        if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
            throw new RuntimeException('That code has just been fully claimed.');
        }

        try {
            $locked->usages()->create([
                'order_id' => $order->getKey(),
                'customer_id' => $order->customer_id,
                'email_hash' => $order->email_hash,
                'discount_cents' => $discount->cents,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // This order already holds a use of this coupon. Not an error, and
            // emphatically not a reason to consume a second one.
            return;
        }

        $locked->increment('used_count');
    }

    /**
     * Hands the use back when an order never became a sale.
     *
     * Safe to call for any order: one that never carried a coupon has no row
     * to delete and nothing is decremented.
     */
    public function release(Order $order): void
    {
        $usage = $order->couponUsage()->lockForUpdate()->first();

        if ($usage === null) {
            return;
        }

        $coupon = Coupon::query()->lockForUpdate()->find($usage->coupon_id);
        $usage->delete();

        // Guarded, so a counter that has somehow reached zero cannot wrap
        // around into four billion uses on an unsigned column.
        if ($coupon !== null && $coupon->used_count > 0) {
            $coupon->decrement('used_count');
        }
    }
}
