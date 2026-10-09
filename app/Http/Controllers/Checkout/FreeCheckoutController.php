<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CompleteOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Coupons\CouponValidator;
use App\Support\BlindIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * A cart worth nothing costs nothing: email, download, done.
 *
 * No payment provider is involved, so no Payment row is written — reports
 * filter on orders.is_free and a free download never pollutes revenue.
 *
 * See §7.6.
 */
class FreeCheckoutController
{
    /** A stranger draining the catalog is the one thing free files invite. */
    private const PER_IP_PER_DAY = 10;

    private const PER_EMAIL_PER_DAY = 5;

    public function __construct(private readonly CartService $cart) {}

    public function __invoke(
        Request $request,
        PlaceOrder $placeOrder,
        CompleteOrder $complete,
    ): RedirectResponse {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:191'],
            'terms' => ['required', 'accepted'],
            'marketing' => ['nullable', 'boolean'],
            'coupon' => ['nullable', 'string', 'max:64'],
        ]);

        $coupon = $data['coupon'] ?? null;

        // A cart of free artwork, or a paid cart a coupon has taken to zero.
        // Either way there is nothing to charge, so PayPal is never involved —
        // it rejects an order of 0.00 outright. See §7.6.
        if ($this->cart->isEmpty() || ! $this->costsNothing($coupon, $data['email'])) {
            return redirect()->route('checkout');
        }

        if ($reason = $this->tooMany($request, $data['email'])) {
            return back()->withInput()->with('error', $reason);
        }

        try {
            $order = $placeOrder($request, $data['email'], (bool) ($data['marketing'] ?? false), $coupon);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();
        $complete($order->refresh());

        $this->cart->clear();

        return redirect()->to(
            URL::temporarySignedRoute('order.success', now()->addDays(7), ['order' => $order->uuid])
        );
    }

    /** Whether this cart, with this code applied, comes to nothing. */
    private function costsNothing(?string $coupon, string $email): bool
    {
        $subtotal = $this->cart->subtotal();

        if ($subtotal->isZero()) {
            return true;
        }

        if ($coupon === null || $coupon === '') {
            return false;
        }

        $result = app(CouponValidator::class)->validate($coupon, $this->cart->products(), $email);

        return $result->valid && $result->discount?->cents >= $subtotal->cents;
    }

    private function tooMany(Request $request, string $email): ?string
    {
        $since = now()->subDay();
        $ipHash = BlindIndex::ip($request->ip());

        $byIp = Order::where('is_free', true)->where('ip_hash', $ipHash)
            ->where('created_at', '>=', $since)->count();

        if ($byIp >= self::PER_IP_PER_DAY) {
            return "That's as many free pieces as we can send in a day. Try again tomorrow.";
        }

        $byEmail = Order::where('is_free', true)->where('email_hash', BlindIndex::email($email))
            ->where('created_at', '>=', $since)->count();

        return $byEmail >= self::PER_EMAIL_PER_DAY
            ? "That's as many free pieces as we can send to one address in a day."
            : null;
    }
}
