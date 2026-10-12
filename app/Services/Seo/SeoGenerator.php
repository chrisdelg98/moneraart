<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Product;
use App\Models\SeoMetadata;
use App\Support\Facades\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Describes a page to a search engine, without anyone having to write it.
 *
 * Generated from what is already on the page, with a hand-written override
 * taking precedence when one exists. A catalogue only stays described as it
 * grows if describing it is not a chore somebody has to remember.
 *
 * See §11.
 */
final class SeoGenerator
{
    /** Google truncates around here. Longer is not wrong, just unread. */
    private const TITLE_LIMIT = 60;

    private const DESCRIPTION_LIMIT = 155;

    public function forProduct(Product $product, string $locale): PageMeta
    {
        $translation = $product->translate($locale);
        $override = $this->override($product, $locale);
        $store = (string) Settings::get('store.name', config('app.name'));

        $title = $override?->seo_title
            ?: $this->trim("{$translation?->title} | {$store}", self::TITLE_LIMIT);

        $description = $override?->seo_description ?: $this->describe($product, $locale);

        return new PageMeta(
            title: $title,
            description: $description,
            canonical: $override?->canonical_url ?: route('product', $translation?->slug),
            robots: $override?->robots ?: $this->storeRobots(),
            image: $override?->og_image_path ?: $product->coverImage?->url(),
            jsonLd: [$this->productSchema($product, $locale, $description)],
        );
    }

    /**
     * A filtered listing describes itself, and points home.
     *
     * Every combination of facets is a URL, and left alone they would be
     * thousands of near-identical pages competing with the one that matters.
     * The canonical sends the weight back to the unfiltered shop. See §11.5.
     *
     * @param  array<string, list<string>>  $filters
     */
    public function forShop(string $term, array $filters, int $page): PageMeta
    {
        $store = (string) Settings::get('store.name', config('app.name'));
        $filtered = $term !== '' || $filters !== [] || $page > 1;

        $meta = new PageMeta(
            title: $this->trim("Shop | {$store}", self::TITLE_LIMIT),
            description: $this->trim(
                'Browse the full collection of digital art, ready to print or keep. '
                .'Instant download, no account needed.',
                self::DESCRIPTION_LIMIT,
            ),
            canonical: route('shop'),
            robots: $this->storeRobots(),
        );

        return $filtered ? $meta->noindex() : $meta;
    }

    public function forPage(string $title, ?string $description = null, bool $indexable = true): PageMeta
    {
        $store = (string) Settings::get('store.name', config('app.name'));

        $meta = new PageMeta(
            title: $this->trim("{$title} | {$store}", self::TITLE_LIMIT),
            description: $description === null ? null : $this->trim($description, self::DESCRIPTION_LIMIT),
            canonical: url()->current(),
            robots: $this->storeRobots(),
        );

        return $indexable ? $meta : $meta->noindex();
    }

    /**
     * A sentence built from what the piece actually is.
     *
     * The product's own subtitle first, because a human wrote it. Failing
     * that, the attributes, which at least say what it is of and where it
     * goes — "Minimalist botanical wall art for a kitchen" beats silence.
     */
    private function describe(Product $product, string $locale): string
    {
        $translation = $product->translate($locale);

        $written = $translation?->subtitle ?: $translation?->description;

        if (filled($written)) {
            return $this->trim((string) $written, self::DESCRIPTION_LIMIT);
        }

        $style = $product->valuesFor('style')->first()?->label($locale);
        $theme = $product->valuesFor('theme')->first()?->label($locale);
        $room = $product->valuesFor('room')->first()?->label($locale);

        $what = collect([$style, $theme])->filter()->implode(' ');
        $where = $room !== null ? " for a {$room}" : '';

        return $this->trim(
            trim("Printable {$what} wall art{$where}.").' Instant download, ready to print or keep.',
            self::DESCRIPTION_LIMIT,
        );
    }

    /**
     * Schema.org Product, so a listing can show a price in the results.
     *
     * @return array<string, mixed>
     */
    private function productSchema(Product $product, string $locale, ?string $description): array
    {
        $translation = $product->translate($locale);
        $price = $product->effectivePrice();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $translation?->title,
            'description' => $description,
            'image' => $product->coverImage?->url(),
            'sku' => $product->uuid,
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => $price->toDecimalString(),
                'priceCurrency' => $price->currency,
                'url' => route('product', $translation?->slug),
                // Files, not stock: a digital product is available or it is
                // not, and it is not when nothing has been uploaded.
                'availability' => $product->file_count > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/PreOrder',
            ]),
        ]);
    }

    private function override(Model $model, string $locale): ?SeoMetadata
    {
        $override = SeoMetadata::query()
            ->where('seoable_type', $model::class)
            ->where('seoable_id', $model->getKey())
            ->where('locale', $locale)
            ->first();

        return $override?->isEmpty() ? null : $override;
    }

    /** A soft launch is a store that is finished and not yet findable. */
    private function storeRobots(): string
    {
        return Settings::get('seo.allow_indexing') ? 'index,follow' : 'noindex,nofollow';
    }

    private function trim(string $text, int $limit): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? $text), $limit, '');
    }
}
