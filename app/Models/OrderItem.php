<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen record of what was sold and for how much.
 *
 * `file_manifest` fixes which product_file ids were bought, so re-uploading or
 * replacing a product's files never alters a past customer's entitlement.
 *
 * @property array<int, int> $file_manifest
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'file_manifest' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents, $this->order->currency);
    }
}
