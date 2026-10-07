<?php

declare(strict_types=1);

namespace App\Services\PayPal;

use App\Models\Order;
use App\Services\Settings\SettingsRepository;
use App\Support\Money;

/**
 * Creating and capturing PayPal orders.
 *
 * Every amount sent is computed server-side from the order already written to
 * our database. The browser sends product ids and a coupon code; nothing about
 * money is taken from it. See §6.4 and §16.7.
 */
final class PayPalOrderService
{
    public function __construct(
        private readonly PayPalClient $client,
        private readonly SettingsRepository $settings,
    ) {}

    /** @return string the PayPal order id */
    public function create(Order $order): string
    {
        $order->loadMissing('items');

        $response = $this->client->request(
            'POST',
            '/v2/checkout/orders',
            $this->payloadFor($order),
            // PayPal's idempotency key, derived deterministically: a retry
            // after a network timeout returns the original order instead of
            // creating a second one.
            ['PayPal-Request-Id' => 'order-'.$order->uuid],
        );

        return (string) ($response['id'] ?? '');
    }

    /** @return array<string, mixed> */
    public function capture(Order $order, string $providerOrderId): array
    {
        return $this->client->request(
            'POST',
            "/v2/checkout/orders/{$providerOrderId}/capture",
            [],
            ['PayPal-Request-Id' => 'capture-'.$order->uuid],
        );
    }

    /** @return array<string, mixed> */
    public function retrieve(string $providerOrderId): array
    {
        return $this->client->request('GET', "/v2/checkout/orders/{$providerOrderId}");
    }

    /** @return array<string, mixed> */
    private function payloadFor(Order $order): array
    {
        $currency = $order->currency;

        $items = $order->items->map(fn ($item): array => [
            'name' => mb_substr($item->title_snapshot, 0, 127),
            'quantity' => (string) $item->quantity,
            'unit_amount' => [
                'currency_code' => $currency,
                'value' => Money::fromCents($item->unit_price_cents, $currency)->toDecimalString(),
            ],
            // Without this PayPal collects a shipping address we neither need
            // nor want to store, and shows the buyer an irrelevant step.
            'category' => 'DIGITAL_GOODS',
        ])->all();

        $itemTotal = Money::fromCents($order->subtotal_cents, $currency);
        $discount = Money::fromCents($order->discount_cents, $currency);

        $breakdown = [
            'item_total' => ['currency_code' => $currency, 'value' => $itemTotal->toDecimalString()],
        ];

        if ($discount->isPositive()) {
            $breakdown['discount'] = ['currency_code' => $currency, 'value' => $discount->toDecimalString()];
        }

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->number,
                // Comes back on the webhook, which is how an event is tied to
                // an order even if our own provider_order_id write failed.
                'custom_id' => $order->uuid,
                'invoice_id' => $order->number,
                'description' => mb_substr($this->describe($order), 0, 127),
                'amount' => [
                    'currency_code' => $currency,
                    // item_total - discount must equal this to the cent, or
                    // PayPal rejects the order. Integer arithmetic throughout.
                    'value' => $order->total()->toDecimalString(),
                    'breakdown' => $breakdown,
                ],
                'items' => $items,
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => mb_substr((string) $this->settings->get('store.name', 'Store'), 0, 127),
                        'shipping_preference' => 'NO_SHIPPING',
                        // "Pay Now" rather than "Continue" measurably reduces
                        // drop-off at the final step.
                        'user_action' => 'PAY_NOW',
                        'locale' => $order->locale === 'es' ? 'es-ES' : 'en-US',
                        'return_url' => route('checkout.return'),
                        'cancel_url' => route('checkout.cancel'),
                    ],
                ],
            ],
        ];
    }

    private function describe(Order $order): string
    {
        $count = $order->items->count();
        $store = (string) $this->settings->get('store.name', 'Store');

        return "{$store} — {$count} digital ".($count === 1 ? 'item' : 'items');
    }
}
