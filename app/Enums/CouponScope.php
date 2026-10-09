<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which cart lines a coupon is allowed to discount.
 *
 * The discount is computed on the matching subset only — a 20% code scoped to
 * one print does not quietly take 20% off the rest of the cart. See §14.2.
 */
enum CouponScope: string
{
    case All = 'all';
    case Products = 'products';
    case Collections = 'collections';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Everything',
            self::Products => 'Selected artwork',
            self::Collections => 'Selected collections',
        };
    }
}
