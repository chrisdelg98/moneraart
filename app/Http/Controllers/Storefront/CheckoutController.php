<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart');
        }

        return view('storefront.checkout', [
            'products' => $this->cart->products(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }
}
