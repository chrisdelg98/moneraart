<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $value */
class AttributeValue extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** @return HasMany<AttributeValueTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_value');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)->label ?? $this->value;
    }

    public function slug(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)->slug ?? $this->value;
    }

    /**
     * An attribute page only earns a crawlable URL once it holds enough
     * products to justify one. See §11.5.
     */
    public function isSeoLanding(int $threshold = 4): bool
    {
        return $this->attribute->is_seo_landing && $this->products_count >= $threshold;
    }
}
