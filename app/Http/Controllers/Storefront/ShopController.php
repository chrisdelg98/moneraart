<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Product;
use Illuminate\View\View;

class ShopController
{
    public function __invoke(): View
    {
        $products = Product::visibleIn(app()->getLocale())
            ->with(['translations', 'coverImage', 'attributeValues.attribute'])
            ->orderByDesc('published_at')
            ->paginate(24);

        return view('storefront.shop', ['products' => $products]);
    }
}
