<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ShopController
{
    public function __invoke(Request $request): View
    {
        $products = Product::visibleIn(app()->getLocale())
            ->with(['translations', 'coverImage', 'attributeValues.attribute.values', 'attributeValues.translations'])
            ->orderByDesc('published_at')
            ->get();

        // Only values something actually uses: a filter for a style no product
        // has is a dead end the visitor has to discover by clicking it.
        $used = $products->flatMap->attributeValues->pluck('id')->unique();

        $facets = Attribute::with(['values' => fn ($q) => $q->whereIn('id', $used)->with('translations')])
            ->whereIn('key', Attribute::MANUAL)
            ->orderBy('position')
            ->get()
            ->filter(fn (Attribute $a): bool => $a->values->isNotEmpty())
            ->mapWithKeys(fn (Attribute $a): array => [$a->key => $a->values]);

        return view('storefront.shop', [
            'products' => $products,
            'facets' => $facets,
            'selected' => $this->selectionFrom($request, $facets),
        ]);
    }

    /**
     * Which facet boxes arrive already ticked.
     *
     * A link from the home page's style tiles should land on the shop with the
     * filter applied, not on the whole catalogue leaving the visitor to find it
     * again. The values are intersected with the real ones, so the query string
     * can only ever select a box that exists.
     *
     * @param  Collection<string, EloquentCollection<int, AttributeValue>>  $facets
     * @return array<string, list<string>>
     */
    private function selectionFrom(Request $request, Collection $facets): array
    {
        return $facets
            ->map(fn (EloquentCollection $values, string $key): array => collect(
                Arr::wrap($request->query($key, [])),
            )
                ->intersect($values->pluck('value'))
                ->values()
                ->all())
            ->filter(fn (array $chosen): bool => $chosen !== [])
            ->all();
    }
}
