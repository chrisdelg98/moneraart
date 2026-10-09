<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Money;

/**
 * How a coupon turns a subtotal into a discount.
 *
 * Both arms work in integer cents, like everything else that touches money.
 * See docs/implementation_plan.md §14.2.
 */
enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage off',
            self::Fixed => 'Fixed amount off',
        };
    }

    /**
     * The discount this coupon produces against a given amount.
     *
     * A fixed discount larger than the amount is clamped rather than left to
     * produce a negative total.
     */
    public function discountOn(Money $amount, int $value): Money
    {
        $cents = match ($this) {
            self::Percent => $amount->percentage($value)->cents,
            self::Fixed => $value,
        };

        return Money::fromCents(min($cents, $amount->cents), $amount->currency);
    }
}
