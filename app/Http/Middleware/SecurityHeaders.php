<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers a browser needs to be told, because it assumes the worst.
 *
 * The storefront gets a content policy that names every source it actually
 * loads — self, and PayPal. That is only possible because the fonts are
 * self-hosted and the one third-party script lives on a single page. See §16.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Laravel's Vite helper puts this on the tags it renders, so the
        // bundles and the dev server's own script are covered by the policy
        // without widening it.
        $nonce = Vite::useCspNonce(Str::random(24));

        $response = $next($request);

        foreach ($this->always() as $header => $value) {
            $response->headers->set($header, $value);
        }

        if ($request->secure()) {
            // Only over HTTPS: announcing this on plain HTTP tells a browser
            // to refuse a site that may be the only one the owner has.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Filament leans on inline handlers and eval, so a policy strict
        // enough to be worth having would stop the admin working. It is
        // behind a login and not the surface an attacker reaches first.
        if (! $this->isAdmin($request)) {
            $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    /** @return array<string, string> */
    private function always(): array
    {
        return [
            // Stops a browser guessing that a .txt is really a script.
            'X-Content-Type-Options' => 'nosniff',
            // Belt to the CSP's braces, for browsers that predate it.
            'X-Frame-Options' => 'DENY',
            // A referrer that carries a signed download path to a third party
            // is the path leaking. Origin only, and only across HTTPS.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // None of these are ever used, and saying so stops an injected
            // script asking on our behalf.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];
    }

    private function policy(string $nonce): string
    {
        $paypal = 'https://www.paypal.com https://www.paypalobjects.com';

        return collect([
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            // Nothing may frame us, which is what stops a clickjacking overlay
            // sitting on top of the add-to-cart button.
            "frame-ancestors 'none'",
            "script-src 'self' 'nonce-{$nonce}' {$paypal}",
            // The artwork component sets a dominant colour per image as a
            // style attribute, so attributes have to be allowed. Stylesheets
            // themselves stay restricted to our own origin.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: {$paypal}",
            "font-src 'self'",
            "connect-src 'self' {$paypal}",
            // PayPal renders its buttons in an iframe of its own.
            "frame-src {$paypal}",
            // Checkout posts here and nowhere else. Without this an injected
            // form could post a customer's address somewhere we do not own.
            "form-action 'self' {$paypal}",
        ])->implode('; ');
    }

    private function isAdmin(Request $request): bool
    {
        return $request->is(trim((string) config('store.admin_path', 'admin'), '/').'*');
    }
}
