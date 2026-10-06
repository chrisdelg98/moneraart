<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Customer-facing text lives in product_translations, not here — see §4.7.1.
 *
 * @property int $id
 * @property string $uuid
 * @property ProductType $type
 * @property int $price_cents
 * @property int|null $sale_price_cents
 * @property ProductStatus $status
 * @property string $currency
 * @property Carbon|null $sale_starts_at
 * @property Carbon|null $sale_ends_at
 * @property Carbon|null $published_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductTranslation> $translations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeValue> $attributeValues
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

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
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'is_ai_generated' => 'boolean',
            'published_at' => 'datetime',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────

    /** @return HasMany<ProductTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /** @return HasMany<ProductFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->orderBy('position');
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /** @return BelongsTo<ProductImage, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'cover_image_id');
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)->withPivot('position');
    }

    /**
     * The pivot is named for how it reads, not for Laravel's alphabetical
     * convention (which would be attribute_value_product), so the table has to
     * be stated explicitly.
     *
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_value');
    }

    /**
     * Child products of a bundle.
     *
     * @return BelongsToMany<Product, $this, Pivot>
     */
    public function bundledProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class, 'bundle_items', 'bundle_product_id', 'child_product_id'
        )->withPivot('position')->orderBy('bundle_items.position');
    }

    // ── Pricing ──────────────────────────────────────────────────────────

    public function price(): Money
    {
        return Money::fromCents($this->price_cents, $this->currency);
    }

    /**
     * The price actually charged right now. A sale only applies inside its
     * window — a sale_price with an elapsed end date is not a discount.
     */
    public function effectivePrice(): Money
    {
        if (! $this->isOnSale()) {
            return $this->price();
        }

        return Money::fromCents((int) $this->sale_price_cents, $this->currency);
    }

    public function isOnSale(): bool
    {
        if ($this->sale_price_cents === null || $this->sale_price_cents >= $this->price_cents) {
            return false;
        }

        $now = now();

        return ! ($this->sale_starts_at?->isAfter($now) ?? false)
            && ! ($this->sale_ends_at?->isBefore($now) ?? false);
    }

    public function isFree(): bool
    {
        return $this->effectivePrice()->isZero();
    }

    // ── Translations ─────────────────────────────────────────────────────

    public function translate(?string $locale = null): ?ProductTranslation
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
     * A product with no published translation in a locale does not appear in
     * that locale at all — no English fallback inside the Spanish storefront.
     * See §4.7.2.
     */
    public function isPublishedIn(string $locale): bool
    {
        return $this->status === ProductStatus::Published
            && $this->translate($locale)?->status === ProductStatus::Published->value;
    }

    // ── Derived attribute helpers ────────────────────────────────────────

    /**
     * Values on one axis — style, room, theme, ratio, orientation or colour.
     *
     * `attribute` is loaded eagerly here rather than touched per value: strict
     * mode treats the lazy alternative as the N+1 it is.
     *
     * @return SupportCollection<int, AttributeValue>
     */
    public function valuesFor(string $attributeKey): SupportCollection
    {
        $this->loadMissing('attributeValues.attribute');

        return $this->attributeValues
            ->filter(fn (AttributeValue $v): bool => $v->attribute->key === $attributeKey)
            ->values();
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** @param Builder<Product> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param Builder<Product> $query */
    public function scopeVisibleIn(Builder $query, string $locale): void
    {
        $query->published()->whereHas(
            'translations',
            fn (Builder $q) => $q->where('locale', $locale)->where('status', 'published')
        );
    }
}
