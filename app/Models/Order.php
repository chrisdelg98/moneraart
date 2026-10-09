<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property OrderStatus $status
 * @property string $number
 * @property int $total_cents
 * @property string $currency
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $terms_accepted_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $completed_at
 * @property string|null $terms_version
 * @property string|null $manual_review_reason
 * @property string|null $admin_notes
 * @property bool $is_free
 */
class Order extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'metadata' => 'array',
            'is_free' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<DownloadGrant, $this> */
    public function downloadGrants(): HasMany
    {
        return $this->hasMany(DownloadGrant::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return HasOne<CouponUsage, $this> */
    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents, $this->currency);
    }

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents, $this->currency);
    }

    public function discount(): Money
    {
        return Money::fromCents($this->discount_cents, $this->currency);
    }

    /**
     * Order numbers come from their own sequence so the series reveals nothing
     * about how many orders the store has taken.
     */
    public static function nextNumber(): string
    {
        $last = self::query()->orderByDesc('id')->value('number');
        $next = $last !== null ? ((int) str($last)->afterLast('-')->toString()) + 1 : 1000;

        return 'MA-'.max($next, 1000);
    }
}
