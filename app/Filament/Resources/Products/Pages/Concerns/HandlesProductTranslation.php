<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Support\Str;

/**
 * The form edits one product in one locale, but title, subtitle, description
 * and slug live in product_translations, and the three manual attributes live
 * in a pivot. This moves them in and out so the form stays flat.
 *
 * See §4.7.1 for why the text is not on the products table.
 */
trait HandlesProductTranslation
{
    /** Fields the form holds but the products table does not. */
    private const TRANSLATED = ['title', 'subtitle', 'description'];

    protected function editingLocale(): string
    {
        return request()->query('locale', (string) config('store.default_locale'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{translation: array<string, mixed>, attributes: array<string, mixed>}
     */
    protected function pullNonProductFields(array &$data): array
    {
        $extracted = ['translation' => [], 'attributes' => []];

        foreach (self::TRANSLATED as $field) {
            $extracted['translation'][$field] = $data[$field] ?? null;
            unset($data[$field]);
        }

        foreach (Attribute::MANUAL as $key) {
            $extracted['attributes'][$key] = $data["attr_{$key}"] ?? null;
            unset($data["attr_{$key}"]);
        }

        // SEO overrides belong to seo_metadata, handled in Phase 7.
        unset($data['seo_title'], $data['seo_description']);

        return $extracted;
    }

    /** @param array{translation: array<string, mixed>, attributes: array<string, mixed>} $extracted */
    protected function saveTranslationAndAttributes(Product $product, array $extracted): void
    {
        // "Leave empty to publish immediately" has to mean that. The storefront
        // scope requires published_at, so a null one hides the product forever.
        if ($product->status === ProductStatus::Published && $product->published_at === null) {
            $product->forceFill(['published_at' => now()])->save();
        }

        $locale = $this->editingLocale();
        $title = (string) ($extracted['translation']['title'] ?? '');

        $product->translations()->updateOrCreate(
            ['locale' => $locale],
            [
                'title' => $title,
                'subtitle' => $extracted['translation']['subtitle'] ?? null,
                'description' => $extracted['translation']['description'] ?? null,
                'slug' => $this->uniqueSlug($product, $locale, $title),
                'status' => $product->status->value,
                // A human typed this, so automation must leave it alone.
                'is_machine_translated' => false,
                'reviewed_at' => now(),
            ],
        );

        // A single-select returns a scalar, a multi-select an array. Flatten
        // both into one list of ids.
        $ids = [];

        foreach ($extracted['attributes'] as $chosen) {
            foreach (is_array($chosen) ? $chosen : [$chosen] as $id) {
                if ($id !== null && $id !== '') {
                    $ids[] = (int) $id;
                }
            }
        }

        $product->attributeValues()->sync(array_unique($ids));
    }

    /**
     * Slugs are unique per locale, not globally — the same artwork is
     * /en/art/coffee-bar and /es/arte/bar-de-cafe. See §4.7.1.
     */
    private function uniqueSlug(Product $product, string $locale, string $title): string
    {
        $base = Str::slug($title) ?: 'artwork';
        $slug = $base;
        $i = 2;

        while (
            ProductTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->where('product_id', '!=', $product->id)
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillNonProductFields(array $data, Product $product): array
    {
        $translation = $product->translate($this->editingLocale());

        foreach (self::TRANSLATED as $field) {
            $data[$field] = $translation?->{$field};
        }

        $product->loadMissing('attributeValues.attribute');

        foreach (Attribute::MANUAL as $key) {
            $values = $product->valuesFor($key)->pluck('id');
            $data["attr_{$key}"] = $key === 'room' ? $values->all() : $values->first();
        }

        return $data;
    }
}
