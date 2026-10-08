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

        // A plain redirect, so the form works with JavaScript disabled. A
        // refusal stays where it happened: sending someone to the cart to be
        // told nothing was added is a round trip for no reason.
        return $outcome->ok
            ? redirect()->route('cart')->with('notice', self::notice('Added to your cart.'))
            : back()->with('notice', self::notice((string) $outcome->reason, isError: true));
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return redirect()->route('cart')->with('notice', self::notice('Removed from your cart.'));
    }

    /**
     * A transient message for the toast.
     *
     * Every cart notice carries a way to the cart, because the one thing
     * someone wants after "already in your cart" is to go and look at it.
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
