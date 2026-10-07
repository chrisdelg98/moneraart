<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Services\Cart\CartService;
use App\Services\PayPal\PayPalClient;
use App\Support\Facades\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PayPalClient $paypal,
    ) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart');
        }

        return view('storefront.checkout', [
            'products' => $this->cart->products(),
            'subtotal' => $this->cart->subtotal(),
            // Null when payments are not configured, so the page can say so
            // rather than rendering buttons that cannot work.
            'paypalClientId' => Settings::get('paypal.enabled') === true
                ? $this->paypal->clientId()
                : null,
            'currency' => (string) config('store.currency', 'USD'),
            'isFree' => $this->cart->subtotal()->isZero(),
        ]);
    }
}
