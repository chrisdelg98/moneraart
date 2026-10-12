<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use App\Services\Seo\SeoGenerator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ShopController
{
    public function __invoke(Request $request, ProductSearch $search, SeoGenerator $seo): View
    {
        $locale = app()->getLocale();
        $facets = $this->facets($locale);

        $selected = $this->selectionFrom($request, $facets);
        $query = ProductSearch::clean($request->query('q'));

        $products = $search->paginate($locale, $query, $selected);

        return view('storefront.shop', [
            'products' => $products,
            'facets' => $facets,
            'selected' => $selected,
            'query' => $query,
            'meta' => $seo->forShop($query, $selected, (int) $request->query('page', 1)),
        ]);
    }

    /**
     * The filter sidebar: only values something actually uses.
     *
     * Built from the whole catalogue rather than from the current results, so
     * the sidebar does not collapse as you narrow and leave you nothing to
     * widen back out with.
     *
     * @return Collection<string, EloquentCollection<int, AttributeValue>>
     */
    private function facets(string $locale): Collection
    {
        // One query for the ids in use, rather than loading every product to
        // ask them. A filter for a style no product has is a dead end the
        // visitor has to discover by clicking it.
        $used = AttributeValue::query()
            ->whereHas('products', fn ($q) => $q->visibleIn($locale))
            ->pluck('id');

        return Attribute::with(['values' => fn ($q) => $q->whereIn('id', $used)->with('translations')])
            ->whereIn('key', Attribute::MANUAL)
            ->orderBy('position')
            ->get()
            ->filter(fn (Attribute $a): bool => $a->values->isNotEmpty())
            ->mapWithKeys(fn (Attribute $a): array => [$a->key => $a->values]);
    }

    /**
     * Which facet boxes arrive already ticked.
     *
     * The values are intersected with the real ones, so the query string can
     * only ever select a box that exists — nothing typed into the URL reaches
     * the query as itself.
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
