<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(): View
    {
        $locale = app()->getLocale();

        $products = Product::visibleIn($locale)
            ->with(['translations', 'coverImage', 'attributeValues.attribute', 'attributeValues.translations'])
            ->orderByDesc('published_at')
            ->take(12)
            ->get();

        $styleAttribute = Attribute::with(['values' => fn ($q) => $q->with('translations')->limit(4)])
            ->where('key', 'style')
            ->first();

        $styles = $styleAttribute !== null ? $styleAttribute->values : collect();

        return view('storefront.home', [
            'styles' => $styles,
            'products' => $products->take(4),
        ]);
    }
}
