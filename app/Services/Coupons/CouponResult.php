<?php

declare(strict_types=1);

namespace App\Services\Coupons;

use App\Models\Coupon;
use App\Support\Money;

/**
 * The answer to "can this code be used on this cart, and for how much?".
 *
 * A rejection carries the sentence shown to the customer. Each failure has its
 * own, because "invalid code" when the real problem is a $20 minimum sends
 * someone away who was one artwork from qualifying. See §14.2.
 */
final readonly class CouponResult
{
    private function __construct(
        public bool $valid,
        public ?Coupon $coupon = null,
        public ?Money $discount = null,
        public ?string $reason = null,
    ) {}

    public static function accepted(Coupon $coupon, Money $discount): self
    {
        return new self(true, $coupon, $discount);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, reason: $reason);
    }
}
