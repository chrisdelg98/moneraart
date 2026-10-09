<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Support\Money;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property string $currency
 * @property CouponScope $applies_to
 * @property int|null $min_subtotal_cents
 * @property int|null $max_discount_cents
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_customer
 * @property int $used_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 *
 * A discount code.
 *
 * The model answers what a coupon *is*; whether it may be used against a
 * particular cart is CouponValidator's job, because that question needs the
 * cart, the customer and a lock. See §14.2.
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'applies_to' => CouponScope::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are stored and compared uppercase, so the index does the work. */
    public static function normalise(string $code): string
    {
        return Str::upper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', self::normalise($code))->first();
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'coupon_collections');
    }

    /** @return HasMany<CouponUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function minSubtotal(): ?Money
    {
        return $this->min_subtotal_cents === null
            ? null
            : Money::fromCents((int) $this->min_subtotal_cents, $this->currency);
    }

    /** How the discount reads to a customer: "20% off" or "$5.00 off". */
    public function describe(): string
    {
        return $this->type === CouponType::Percent
            ? "{$this->value}% off"
            : Money::fromCents((int) $this->value, $this->currency)->format().' off';
    }
}
