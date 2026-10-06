<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $locale
 * @property string $title
 * @property string $slug
 */
class ProductTranslation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_machine_translated' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** A machine draft nobody has approved yet. */
    public function needsReview(): bool
    {
        return $this->is_machine_translated && $this->reviewed_at === null;
    }
}
