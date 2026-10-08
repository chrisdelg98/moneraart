<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Services\Cart\CartService;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductController
{
    public function __construct(private readonly CartService $cart) {}

    public function __invoke(string $slug): View
    {
        $locale = app()->getLocale();

        $translation = ProductTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();

        $product = $translation->product()
            ->with(['translations', 'images', 'files', 'attributeValues.attribute', 'attributeValues.translations'])
            ->firstOrFail();

        // An archived product is gone on purpose: 410 de-indexes faster than a
        // 404 and tells a crawler the removal was intentional. See §11.3.
        if ($product->status === ProductStatus::Archived) {
            throw new HttpException(410, 'This artwork is no longer available.');
        }

        if (! $product->isPublishedIn($locale)) {
            abort(404);
        }

        $related = Product::visibleIn($locale)
            ->whereKeyNot($product->getKey())
            ->whereHas('attributeValues', fn ($q) => $q->whereIn(
                'attribute_values.id', $product->attributeValues->pluck('id')
            ))
            ->with(['translations', 'coverImage'])
            ->take(4)
            ->get();

        return view('storefront.product', [
            'product' => $product,
            'translation' => $translation,
            'related' => $related,
            // The one page that states cart membership. It costs this page the
            // byte-identical HTML the catalogue keeps for caching, which is
            // the trade for a buy button that knows what it already holds.
            'inCart' => $this->cart->has($product),
        ]);
    }
}
