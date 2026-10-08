<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Cart\CartService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the badge cookie honest.
 *
 * CartService writes the cookie when the cart changes, which leaves every way
 * the two can drift apart unhandled: the cookie expiring while the session
 * lives on, a visitor clearing site data, or a value written under an older
 * scheme that the badge script can no longer parse.
 *
 * Correcting it here costs one session read and sets a header only when the
 * two disagree, so the HTML stays byte-identical for every visitor and the
 * page remains cacheable. See §7.1.
 */
class SyncCartCountCookie
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The badge only exists on a rendered page, and CartService has
        // already queued the right value when the cart changed this request.
        if (! $request->isMethod('GET') || Cookie::hasQueued('cart_count')) {
            return $response;
        }

        $count = (string) $this->cart->count();

        if ($request->cookie('cart_count') !== $count) {
            Cookie::queue(Cookie::make(
                name: 'cart_count',
                value: $count,
                minutes: 60 * 24 * 30,
                httpOnly: false,
            ));
        }

        return $response;
    }
}
