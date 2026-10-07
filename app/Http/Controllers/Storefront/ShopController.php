<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\View\View;

class ShopController
{
    public function __invoke(): View
    {
        $products = Product::visibleIn(app()->getLocale())
            ->with(['translations', 'coverImage', 'attributeValues.attribute.values'])
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

        return view('storefront.shop', compact('products', 'facets'));
    }
}
