<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Cart\CartService;
use App\Support\Facades\Settings;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);

    Settings::setMany([
        'paypal.mode' => 'sandbox',
        'paypal.sandbox.client_id' => 'test-client',
        'paypal.sandbox.client_secret' => 'test-secret',
        'paypal.enabled' => true,
    ]);

    $this->cart = app(CartService::class);
});

/** PayPal creating an order and then returning a capture for it. */
function fakePayPalCheckout(string $capturedValue = '5.99', string $status = 'COMPLETED'): void
{
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        '*/v2/checkout/orders/*/capture' => Http::response([
            'id' => '5O190127TN364715T',
            'status' => $status,
            'payer' => ['email_address' => 'buyer@example.com'],
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => '3C679366HH908993F',
                    'status' => $status,
                    'amount' => ['currency_code' => 'USD', 'value' => $capturedValue],
                ]]],
            ]],
        ]),
        '*/v2/checkout/orders' => Http::response(['id' => '5O190127TN364715T', 'status' => 'CREATED']),
    ]);
}

/** @return array{0: Order, 1: string} */
function startCheckout(CartService $cart, int $cents = 599): array
{
    $product = sellable($cents);
    $cart->add($product);

    return [$product, '5O190127TN364715T'];
}

it('creates an order before contacting PayPal', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart);

    $response = $this->postJson(route('checkout.paypal.create'), [
        'email' => 'buyer@example.com',
        'terms' => 1,
    ])->assertOk();

    $order = Order::firstOrFail();

    // A captured payment must never arrive for an order that does not exist.
    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_cents)->toBe(599)
        ->and($order->metadata['paypal_order_id'])->toBe('5O190127TN364715T')
        ->and($response->json('orderUuid'))->toBe($order->uuid);
});

it('computes the total from the catalog, never from the request', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart, 1299);

    // A tampered amount in the body must change nothing.
    $this->postJson(route('checkout.paypal.create'), [
        'email' => 'buyer@example.com',
        'terms' => 1,
        'total' => 1,
        'price' => '0.01',
    ])->assertOk();

    expect(Order::firstOrFail()->total_cents)->toBe(1299);
});

it('refuses checkout without accepted terms', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart);

    // The disabled button is a convenience; this is the control. See §7.7.1.
    $this->postJson(route('checkout.paypal.create'), ['email' => 'buyer@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('terms');

    expect(Order::count())->toBe(0);
});

it('refuses checkout without a valid email', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart);

    $this->postJson(route('checkout.paypal.create'), ['email' => 'not-an-email', 'terms' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('refuses checkout with an empty cart', function (): void {
    fakePayPalCheckout();

    $this->postJson(route('checkout.paypal.create'), ['email' => 'buyer@example.com', 'terms' => 1])
        ->assertStatus(422);
});

it('freezes what was sold on the order line', function (): void {
    fakePayPalCheckout();
    [$product] = startCheckout($this->cart, 599);

    $this->postJson(route('checkout.paypal.create'), ['email' => 'buyer@example.com', 'terms' => 1]);

    $item = Order::firstOrFail()->items()->firstOrFail();

    expect($item->title_snapshot)->toBe($product->title('en'))
        ->and($item->unit_price_cents)->toBe(599)
        ->and($item->file_manifest)->toHaveCount(1);

    // Changing the catalog afterwards must not change the sale.
    $product->update(['price_cents' => 9999]);

    expect($item->fresh()->unit_price_cents)->toBe(599);
});

it('captures, verifies and completes the purchase', function (): void {
    fakePayPalCheckout('5.99');
    startCheckout($this->cart, 599);

    $create = $this->postJson(route('checkout.paypal.create'), [
        'email' => 'buyer@example.com', 'terms' => 1,
    ])->json();

    $response = $this->postJson(route('checkout.paypal.capture'), [
        'orderUuid' => $create['orderUuid'],
        'paypalOrderId' => $create['paypalOrderId'],
    ])->assertOk();

    $order = Order::firstOrFail();

    // Paid is not the end state: grants are issued and the email queued, so a
    // successful capture lands on completed.
    expect($order->status)->toBe(OrderStatus::Completed)
        ->and(DownloadGrant::count())->toBe(1)
        ->and(Payment::count())->toBe(1)
        ->and($response->json('redirect'))->toContain('/order/'.$order->uuid)
        // The cart is only cleared once the money is confirmed.
        ->and($this->cart->isEmpty())->toBeTrue();
});

it('sends a mismatched capture to manual review instead of completing it', function (): void {
    fakePayPalCheckout('0.01');
    startCheckout($this->cart, 599);

    $create = $this->postJson(route('checkout.paypal.create'), [
        'email' => 'buyer@example.com', 'terms' => 1,
    ])->json();

    $this->postJson(route('checkout.paypal.capture'), [
        'orderUuid' => $create['orderUuid'],
        'paypalOrderId' => $create['paypalOrderId'],
    ])->assertOk();

    expect(Order::firstOrFail()->status)->toBe(OrderStatus::ManualReview)
        ->and(Payment::count())->toBe(0)
        // The cart stays put: nothing was sold.
        ->and($this->cart->isEmpty())->toBeFalse();
});

it('says so plainly when PayPal is unreachable, without losing the order', function (): void {
    startCheckout($this->cart);
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        '*/v2/checkout/orders' => Http::response([], 500),
    ]);

    $this->postJson(route('checkout.paypal.create'), ['email' => 'buyer@example.com', 'terms' => 1])
        ->assertStatus(503)
        // The customer is told nothing was charged, which is the fact that
        // matters most at this moment.
        ->assertJsonFragment(['message' => "PayPal isn't responding at the moment. Give it a few seconds and try again — nothing has been charged."]);

    // The order survives for the reconciliation sweep to pick up.
    expect(Order::count())->toBe(1);
});

it('records marketing consent only when it was given', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart);

    $this->postJson(route('checkout.paypal.create'), [
        'email' => 'buyer@example.com', 'terms' => 1,
    ]);

    expect(Order::firstOrFail()->customer->marketing_consent)->toBeFalse();
});

it('records the accepted terms version on the order', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart);

    $this->postJson(route('checkout.paypal.create'), ['email' => 'buyer@example.com', 'terms' => 1]);

    $order = Order::firstOrFail();

    // "What did this buyer agree to, on this date" has to stay answerable.
    expect($order->terms_version)->not->toBeNull()
        ->and($order->terms_accepted_at)->not->toBeNull()
        ->and($order->terms_accepted_ip_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('shows the order page only with a valid signature', function (): void {
    fakePayPalCheckout();
    startCheckout($this->cart);

    $create = $this->postJson(route('checkout.paypal.create'), [
        'email' => 'buyer@example.com', 'terms' => 1,
    ])->json();

    $this->postJson(route('checkout.paypal.capture'), [
        'orderUuid' => $create['orderUuid'],
        'paypalOrderId' => $create['paypalOrderId'],
    ]);

    $order = Order::firstOrFail();

    $this->get(URL::temporarySignedRoute('order.success', now()->addDays(7), ['order' => $order->uuid]))
        ->assertOk()
        ->assertSee($order->number);

    // Guessing the uuid is not enough.
    $this->get('/order/'.$order->uuid)->assertForbidden();
});

it('hides the PayPal buttons when payments are not enabled', function (): void {
    Settings::set('paypal.enabled', false);
    startCheckout($this->cart);

    $this->get(route('checkout'))->assertOk()
        ->assertSee('Payments are not switched on yet')
        ->assertDontSee('paypal.com/sdk/js', escape: false);
});
