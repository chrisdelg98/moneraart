<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Collections reuse existing product assets rather than duplicating files.
 * A product may belong to several.
 *
 * @property bool $is_indexable
 * @property ProductStatus $status
 * @property int $min_products_for_index
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CollectionTranslation> $translations
 */
class Collection extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'rules' => 'array',
            'is_indexable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<CollectionTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CollectionTranslation::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('position')
            ->orderBy('collection_product.position');
    }

    /** @return BelongsTo<ProductImage, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'cover_image_id');
    }

    public function translate(?string $locale = null): ?CollectionTranslation
    {
        $locale ??= app()->getLocale();

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();
    }

    public function title(?string $locale = null): string
    {
        return $this->translate($locale)->title ?? '';
    }

    /**
     * Indexability is maintained by a nightly job, both ways: a collection that
     * drops below the threshold loses its crawlable page and leaves the
     * sitemap. Two products do not justify a landing page. See §11.5.
     */
    public function qualifiesForIndexing(): bool
    {
        return $this->status === ProductStatus::Published
            && $this->products()->published()->count() >= $this->min_products_for_index;
    }

    /** @param Builder<Collection> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }
}
