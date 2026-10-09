<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(): View
    {
        $locale = app()->getLocale();

        $products = Product::visibleIn($locale)
            ->with(['translations', 'coverImage', 'attributeValues.attribute', 'attributeValues.translations'])
            ->orderByDesc('published_at')
            ->take(4)
            ->get();

        return view('storefront.home', [
            'styles' => $this->styleTiles($locale),
            'products' => $products,
        ]);
    }

    /**
     * Four styles to browse by, each with a real piece behind it.
     *
     * Drawn at random from the styles that actually have something to show, so
     * the page differs between visits and a style nobody has filed artwork
     * under never offers the visitor an empty shelf.
     *
     * @return Collection<int, array{value: AttributeValue, cover: ProductImage|null}>
     */
    private function styleTiles(string $locale): Collection
    {
        $style = Attribute::query()->where('key', 'style')->first();

        if ($style === null) {
            return collect();
        }

        $values = AttributeValue::query()
            ->where('attribute_id', $style->getKey())
            ->whereHas('products', fn ($q) => $q->visibleIn($locale))
            ->with('translations')
            ->inRandomOrder()
            ->take(4)
            ->get();

        if ($values->isEmpty()) {
            return collect();
        }

        $covers = $this->coversFor($values, $locale);

        return $values->toBase()->map(fn (AttributeValue $value): array => [
            'value' => $value,
            'cover' => $covers[$value->getKey()] ?? null,
        ]);
    }

    /**
     * One cover image per style, resolved in a single query.
     *
     * Asking each tile for its own artwork in the template is four more
     * queries, and four more again for their images.
     *
     * @param  EloquentCollection<int, AttributeValue>  $values
     * @return array<int, ProductImage|null>
     */
    private function coversFor(EloquentCollection $values, string $locale): array
    {
        $ids = $values->modelKeys();

        $products = Product::visibleIn($locale)
            ->whereHas('attributeValues', fn ($q) => $q->whereIn('attribute_values.id', $ids))
            ->with(['coverImage', 'attributeValues'])
            ->orderByDesc('published_at')
            ->get();

        $covers = [];

        foreach ($ids as $id) {
            $covers[$id] = $products
                ->first(fn (Product $p): bool => $p->attributeValues->contains('id', $id))
                ?->coverImage;
        }

        return $covers;
    }
}
