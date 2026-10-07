<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\MarkOrderPaid;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\PayPal\PayPalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The two calls the PayPal buttons make.
 *
 * `create` writes our order first and then asks PayPal for one; `capture`
 * verifies what came back before anything is fulfilled. The browser sends an
 * email and a consent box — never a price. See §7.3 and §16.7.
 */
class PayPalCheckoutController
{
    public function __construct(private readonly CartService $cart) {}

    public function create(Request $request, PlaceOrder $placeOrder, PayPalOrderService $paypal): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:191'],
            // Re-validated server-side: a disabled button is a convenience,
            // not a control. See §7.7.1.
            'terms' => ['required', 'accepted'],
            'marketing' => ['nullable', 'boolean'],
        ]);

        try {
            $order = $placeOrder($request, $data['email'], (bool) ($data['marketing'] ?? false));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $paypalOrderId = $paypal->create($order);
        } catch (Throwable $e) {
            Log::channel(config('logging.default'))->error('paypal.create_failed', [
                'order' => $order->number,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "PayPal isn't responding at the moment. Give it a few seconds and "
                    .'try again — nothing has been charged.',
            ], 503);
        }

        $order->forceFill([
            'metadata' => [...($order->metadata ?? []), 'paypal_order_id' => $paypalOrderId],
        ])->save();

        return response()->json([
            'paypalOrderId' => $paypalOrderId,
            'orderUuid' => $order->uuid,
        ]);
    }

    public function capture(
        Request $request,
        PayPalOrderService $paypal,
        MarkOrderPaid $markPaid,
        CompleteOrder $complete,
    ): JsonResponse {
        $data = $request->validate([
            'orderUuid' => ['required', 'uuid'],
            'paypalOrderId' => ['required', 'string', 'max:64'],
        ]);

        $order = Order::where('uuid', $data['orderUuid'])->firstOrFail();

        try {
            $response = $paypal->capture($order, $data['paypalOrderId']);
        } catch (Throwable $e) {
            Log::channel(config('logging.default'))->error('paypal.capture_failed', [
                'order' => $order->number,
                'message' => $e->getMessage(),
            ]);

            // The webhook is the backstop: if the money did move, it arrives
            // anyway and fulfils the order. See §6.8.
            return response()->json([
                'message' => "We're still confirming this payment with PayPal. The moment it "
                    .'clears, your files go straight to your inbox. Nothing further is needed '
                    .'from you.',
            ], 502);
        }

        $capture = $this->captureResource($response);

        if ($capture === null) {
            // What went wrong is our problem to diagnose, not the customer's
            // to read. The log carries the detail; they get a next step.
            return response()->json([
                'message' => "We couldn't finish this payment. Please try again, or write to us "
                    .'if it keeps happening.',
            ], 502);
        }

        $outcome = $markPaid($order, $capture, 'capture');

        if (! $outcome->isSuccessful()) {
            return response()->json([
                'message' => $outcome->result === 'manual_review'
                    ? 'Your payment is going through a quick check on our side. We will email '
                        .'your files as soon as it clears, usually within a few hours.'
                    : "That payment didn't go through, and nothing was charged. You can try "
                        .'again from your cart.',
                'redirect' => URL::signedRoute('order.success', ['order' => $order->uuid]),
            ], 200);
        }

        // Paid is not delivered. Grants and the email happen here.
        $complete($outcome->order);

        $this->cart->clear();

        return response()->json([
            'redirect' => URL::temporarySignedRoute('order.success', now()->addDays(7), ['order' => $order->uuid]),
        ]);
    }

    /**
     * Digs the capture out of PayPal's nesting.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function captureResource(array $response): ?array
    {
        $capture = data_get($response, 'purchase_units.0.payments.captures.0');

        if (! is_array($capture)) {
            return null;
        }

        // The payer sits on the order, not the capture, but MarkOrderPaid wants
        // one shape whether it came from here or from a webhook.
        $capture['payer'] ??= $response['payer'] ?? null;

        return $capture;
    }
}
