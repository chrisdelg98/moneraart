<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Services\Cart\CartService;
use App\Services\Coupons\CouponValidator;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Apply button: a preview, and only a preview.
 *
 * Nothing here is remembered and nothing here decides a price. The code is
 * checked again inside the transaction that writes the order, which is the
 * only place a discount is ever real. See §14.2 and §16.7.
 */
class CouponController
{
    public function __invoke(
        Request $request,
        CartService $cart,
        CouponValidator $validator,
    ): JsonResponse {
        $data = $request->validate([
            'coupon' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
        ]);

        $products = $cart->products();

        if ($products->isEmpty()) {
            return response()->json(['valid' => false, 'message' => 'Your cart is empty.'], 422);
        }

        $result = $validator->validate($data['coupon'], $products, $data['email'] ?? null);

        if (! $result->valid) {
            return response()->json(['valid' => false, 'message' => $result->reason], 422);
        }

        $subtotal = $cart->subtotal();
        $discount = $result->discount ?? Money::zero($subtotal->currency);
        $total = $subtotal->minus($discount)->clampToZero();

        return response()->json([
            'valid' => true,
            'code' => $result->coupon?->code,
            'message' => $result->coupon?->describe().' applied.',
            'discount' => $discount->format(),
            'total' => $total->format(),
            // The integer, so the page can test for "nothing left to charge"
            // without parsing a currency string back into a number.
            'totalCents' => $total->cents,
        ]);
    }
}
