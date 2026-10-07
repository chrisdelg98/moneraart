<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CartController
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): View
    {
        return view('storefront.cart', [
            'products' => $this->cart->products(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function add(Product $product): RedirectResponse
    {
        $outcome = $this->cart->add($product);

        // A plain redirect back, so the form works with JavaScript disabled.
        return $outcome->ok
            ? redirect()->route('cart')->with('status', 'Added to your cart.')
            : back()->with('error', $outcome->reason);
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return redirect()->route('cart')->with('status', 'Removed from your cart.');
    }
}
