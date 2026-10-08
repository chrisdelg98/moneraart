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

        // Back where they were, never to the cart. Someone browsing a grid
        // wants to carry on browsing; the toast offers the trip to the cart
        // to whoever actually wants it. A plain redirect, so the form still
        // works with JavaScript disabled.
        return back()->with('notice', $outcome->ok
            ? self::notice('Added to your cart.')
            : self::notice((string) $outcome->reason, isError: true));
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return back()->with('notice', self::notice('Removed from your cart.'));
    }

    /**
     * A transient message for the toast.
     *
     * The way to the cart travels with every notice; the toast drops it when
     * the visitor is already looking at the cart.
     *
     * @return array{text: string, isError: bool, url: string, label: string}
     */
    private static function notice(string $text, bool $isError = false): array
    {
        return [
            'text' => $text,
            'isError' => $isError,
            'url' => route('cart'),
            'label' => 'View cart',
        ];
    }
}
