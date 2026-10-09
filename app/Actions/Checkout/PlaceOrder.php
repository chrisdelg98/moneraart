<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Coupons\CouponLedger;
use App\Services\Coupons\CouponValidator;
use App\Support\BlindIndex;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes the order before PayPal is ever contacted.
 *
 * The order exists first on purpose: a captured payment must never arrive for
 * an order that does not exist. Every amount is computed here from the catalog,
 * and the line items freeze what was sold — later edits to a product cannot
 * change what a past customer bought or is entitled to. See §7.3.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CouponValidator $coupons,
        private readonly CouponLedger $ledger,
    ) {}

    public function __invoke(
        Request $request,
        string $email,
        bool $marketingConsent = false,
        ?string $couponCode = null,
    ): Order {
        $products = $this->cart->products();

        if ($products->isEmpty()) {
            throw new RuntimeException('Your cart is empty.');
        }

        return DB::transaction(function () use ($request, $products, $email, $marketingConsent, $couponCode): Order {
            $locale = app()->getLocale();
            $customer = Customer::forEmail($email, $locale);

            if ($marketingConsent && ! $customer->marketing_consent) {
                $customer->forceFill(['marketing_consent' => true])->save();
            }

            $currency = (string) config('store.currency', 'USD');
            $subtotal = Money::zero($currency);

            $order = Order::create([
                'number' => Order::nextNumber(),
                'customer_id' => $customer->getKey(),
                'status' => OrderStatus::Pending,
                'currency' => $currency,
                'email_hash' => $customer->email_hash,
                // IP and user agent are hashed, never stored. They exist for
                // abuse detection and dispute evidence, not for profiling.
                'ip_hash' => BlindIndex::ip($request->ip()),
                'user_agent_hash' => BlindIndex::userAgent($request->userAgent()),
                'locale' => $locale,
                'placed_at' => now(),
                // Which revision this buyer accepted. A policy edited since the
                // sale is worthless as evidence. See §7.7.2.
                'terms_version' => (string) config('store.terms_version', '1.0'),
                'terms_accepted_at' => now(),
                'terms_accepted_ip_hash' => BlindIndex::ip($request->ip()),
                'subtotal_cents' => 0,
                'total_cents' => 0,
            ]);

            foreach ($products as $product) {
                $price = $product->effectivePrice();
                $subtotal = $subtotal->plus($price);

                $order->items()->create([
                    'product_id' => $product->getKey(),
                    'product_type' => $product->type->value,
                    'title_snapshot' => $product->title($locale),
                    'slug_snapshot' => (string) $product->translate($locale)?->slug,
                    'unit_price_cents' => $price->cents,
                    'quantity' => 1,
                    'total_cents' => $price->cents,
                    // Freezes the entitlement: re-uploading a product's files
                    // later never alters what this customer bought.
                    'file_manifest' => $this->manifest($product),
                    'created_at' => now(),
                ]);
            }

            // Re-validated here rather than trusted from the Apply step: the
            // only check that matters is the one inside the transaction that
            // writes the amount. A code that expired or ran out in between is
            // refused now, before anyone is charged.
            $discount = Money::zero($currency);

            if ($couponCode !== null && $couponCode !== '') {
                $result = $this->coupons->validate($couponCode, $products, $email);

                if (! $result->valid) {
                    throw new RuntimeException((string) $result->reason);
                }

                $discount = $result->discount ?? Money::zero($currency);

                $this->ledger->reserve($result->coupon, $order, $discount);

                $order->forceFill([
                    'coupon_id' => $result->coupon->getKey(),
                    // Kept alongside the id so a deleted coupon still reads on
                    // the receipt as the code the customer actually typed.
                    'coupon_code' => $result->coupon->code,
                ]);
            }

            $total = $subtotal->minus($discount)->clampToZero();

            $order->forceFill([
                'subtotal_cents' => $subtotal->cents,
                'discount_cents' => $discount->cents,
                'total_cents' => $total->cents,
                'is_free' => $total->isZero(),
            ])->save();

            return $order->refresh();
        });
    }

    /**
     * A bundle delivers the union of its children's files, deduplicated — one
     * byte on disk, many entitlements. See §8.3.
     *
     * @return list<int>
     */
    private function manifest(Product $product): array
    {
        if ($product->type->hasChildren()) {
            $product->loadMissing('bundledProducts.files');

            return $product->bundledProducts
                ->flatMap(fn (Product $child) => $child->files)
                ->unique('checksum_sha256')
                ->pluck('id')
                ->values()
                ->all();
        }

        $product->loadMissing('files');

        return $product->files->pluck('id')->values()->all();
    }
}
