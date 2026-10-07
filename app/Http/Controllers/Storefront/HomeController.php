<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

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

        return view('storefront.home', [
            'featured' => $products->first(),
            'products' => $products->skip(1),
        ]);
    }
}
