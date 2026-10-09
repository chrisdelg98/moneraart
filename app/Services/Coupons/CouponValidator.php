<?php

declare(strict_types=1);

namespace App\Services\Coupons;

use App\Enums\CouponScope;
use App\Models\Coupon;
use App\Models\Product;
use App\Support\BlindIndex;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Decides whether a code applies to a cart, and for how much.
 *
 * The checks run in the order §14.2 sets out and stop at the first failure, so
 * the message a customer sees names the actual obstacle.
 *
 * This runs twice for every purchase: once when the code is typed, and again
 * inside the transaction that writes the order. The second time is the one that
 * counts — a coupon that expires, or runs out of uses, between the two is a
 * routinely exploited race, and only the later check sees it.
 */
final class CouponValidator
{
    /**
     * @param  Collection<int, Product>  $products  the cart, with live prices
     */
    public function validate(string $code, Collection $products, ?string $email = null): CouponResult
    {
        $coupon = Coupon::findByCode($code);

        if ($coupon === null || ! $coupon->is_active) {
            return CouponResult::rejected("We don't recognise that code.");
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            return CouponResult::rejected('That code is not active yet.');
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            return CouponResult::rejected('That code has expired.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return CouponResult::rejected('That code has been fully claimed.');
        }

        if ($this->perCustomerLimitReached($coupon, $email)) {
            return CouponResult::rejected("You've already used that code.");
        }

        $currency = (string) config('store.currency', 'USD');
        $subtotal = $this->sum($products, $currency);
        $minimum = $coupon->minSubtotal();

        if ($minimum !== null && $subtotal->cents < $minimum->cents) {
            return CouponResult::rejected("That code needs a subtotal of {$minimum->format()} or more.");
        }

        // The discount is computed on the lines the coupon actually covers,
        // never on the whole cart.
        $eligible = $this->eligibleFor($coupon, $products);

        if ($eligible->isEmpty()) {
            return CouponResult::rejected("That code doesn't apply to anything in your cart.");
        }

        $discount = $coupon->type->discountOn($this->sum($eligible, $currency), (int) $coupon->value);

        if ($coupon->max_discount_cents !== null) {
            $discount = Money::fromCents(
                min($discount->cents, (int) $coupon->max_discount_cents),
                $currency,
            );
        }

        // A discount that reaches zero is not a discount, and a total can never
        // go below zero.
        $discount = Money::fromCents(min($discount->cents, $subtotal->cents), $currency);

        if (! $discount->isPositive()) {
            return CouponResult::rejected("That code doesn't reduce this cart.");
        }

        return CouponResult::accepted($coupon, $discount);
    }

    private function perCustomerLimitReached(Coupon $coupon, ?string $email): bool
    {
        if ($coupon->usage_limit_per_customer === null || $email === null || $email === '') {
            return false;
        }

        // Counted on the hash, so the check survives a customer record being
        // erased and never needs the address in clear.
        return $coupon->usages()
            ->where('email_hash', BlindIndex::email($email))
            ->count() >= $coupon->usage_limit_per_customer;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function eligibleFor(Coupon $coupon, Collection $products): Collection
    {
        return match ($coupon->applies_to) {
            CouponScope::All => $products,

            CouponScope::Products => $products->filter(
                fn (Product $p): bool => $coupon->products->contains('id', $p->getKey()),
            )->values(),

            CouponScope::Collections => $products->filter(
                fn (Product $p): bool => $p->collections->pluck('id')
                    ->intersect($coupon->collections->pluck('id'))
                    ->isNotEmpty(),
            )->values(),
        };
    }

    /** @param Collection<int, Product> $products */
    private function sum(Collection $products, string $currency): Money
    {
        return $products->reduce(
            fn (Money $carry, Product $p): Money => $carry->plus($p->effectivePrice()),
            Money::zero($currency),
        );
    }
}
