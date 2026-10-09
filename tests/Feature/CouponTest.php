<?php

declare(strict_types=1);

use App\Actions\Checkout\PlaceOrder;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\Cart\CartService;
use App\Services\Coupons\CouponLedger;
use App\Services\Coupons\CouponValidator;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);

    $this->cart = app(CartService::class);
    $this->validator = app(CouponValidator::class);
});

/** Validate a code against a cart holding one product at the given price. */
function checkCode(string $code, int $cents = 1000, ?string $email = null): object
{
    $cart = app(CartService::class);
    $cart->add(sellable($cents));

    return app(CouponValidator::class)->validate($code, $cart->products(), $email);
}

it('discounts by percentage, rounding in whole cents', function (): void {
    Coupon::factory()->percent(20)->create(['code' => 'TWENTY']);

    // 999 * 20% = 199.8, which must land on a cent, not a float.
    $result = checkCode('TWENTY', 999);

    expect($result->valid)->toBeTrue()
        ->and($result->discount->cents)->toBe(200);
});

it('discounts by a fixed amount', function (): void {
    Coupon::factory()->fixed(250)->create(['code' => 'FIVER']);

    expect(checkCode('FIVER', 1000)->discount->cents)->toBe(250);
});

it('never discounts more than the cart is worth', function (): void {
    Coupon::factory()->fixed(5000)->create(['code' => 'HUGE']);

    // A total below zero is money owed to the customer.
    expect(checkCode('HUGE', 1000)->discount->cents)->toBe(1000);
});

it('caps the discount at the configured ceiling', function (): void {
    Coupon::factory()->percent(50)->create(['code' => 'HALF', 'max_discount_cents' => 300]);

    expect(checkCode('HALF', 2000)->discount->cents)->toBe(300);
});

it('matches a code whatever case it is typed in', function (): void {
    Coupon::factory()->percent(10)->create(['code' => 'SPRING']);

    expect(checkCode('  spring  ')->valid)->toBeTrue();
});

it('refuses a code that does not exist, is inactive, expired or used up', function (): void {
    Coupon::factory()->inactive()->create(['code' => 'OFF']);
    Coupon::factory()->expired()->create(['code' => 'OLD']);
    Coupon::factory()->usedUp()->create(['code' => 'GONE']);

    expect(checkCode('NOSUCHCODE')->valid)->toBeFalse()
        ->and(checkCode('OFF')->valid)->toBeFalse()
        ->and(checkCode('OLD')->reason)->toContain('expired')
        ->and(checkCode('GONE')->reason)->toContain('claimed');
});

it('refuses a code below its minimum subtotal, and says what the minimum is', function (): void {
    Coupon::factory()->percent(10)->create(['code' => 'BIG', 'min_subtotal_cents' => 2000]);

    // The message names the obstacle: someone one artwork short of qualifying
    // should be told so, not told the code is invalid.
    expect(checkCode('BIG', 1000)->reason)->toContain('$20.00');
});

it('discounts only the artwork a scoped code covers', function (): void {
    $covered = sellable(1000);
    $other = sellable(1000);

    $coupon = Coupon::factory()->percent(50)->create(['code' => 'ONEONLY', 'applies_to' => 'products']);
    $coupon->products()->attach($covered);

    $this->cart->add($covered);
    $this->cart->add($other);

    // 50% of the covered piece alone, never of the pair.
    expect($this->validator->validate('ONEONLY', $this->cart->products())->discount->cents)
        ->toBe(500);
});

it('refuses a scoped code when nothing in the cart matches', function (): void {
    $coupon = Coupon::factory()->percent(50)->create(['code' => 'ELSEWHERE', 'applies_to' => 'products']);
    $coupon->products()->attach(sellable(1000));

    $this->cart->add(sellable(1000));

    expect($this->validator->validate('ELSEWHERE', $this->cart->products())->reason)
        ->toContain("doesn't apply");
});

it('writes the discount onto the order and reserves the use', function (): void {
    $coupon = Coupon::factory()->percent(25)->create(['code' => 'QUARTER']);
    $this->cart->add(sellable(1000));

    $order = app(PlaceOrder::class)(request(), 'buyer@example.com', false, 'QUARTER');

    expect($order->subtotal_cents)->toBe(1000)
        ->and($order->discount_cents)->toBe(250)
        ->and($order->total_cents)->toBe(750)
        ->and($order->coupon_code)->toBe('QUARTER')
        // Reserved at placement, not at payment: the limit has to hold while
        // the customer is away at PayPal.
        ->and($coupon->refresh()->used_count)->toBe(1)
        ->and(CouponUsage::where('order_id', $order->id)->exists())->toBeTrue();
});

it('refuses to place an order with a code that expired since it was typed', function (): void {
    $coupon = Coupon::factory()->percent(25)->create(['code' => 'LAPSED']);
    $this->cart->add(sellable(1000));

    // The race the second validation exists for.
    $coupon->update(['expires_at' => now()->subMinute()]);

    expect(fn () => app(PlaceOrder::class)(request(), 'buyer@example.com', false, 'LAPSED'))
        ->toThrow(RuntimeException::class, 'expired');
});

it('counts a per-customer limit on the email, not the customer row', function (): void {
    Coupon::factory()->percent(10)->create(['code' => 'ONCE', 'usage_limit_per_customer' => 1]);

    $this->cart->add(sellable(1000));
    app(PlaceOrder::class)(request(), 'buyer@example.com', false, 'ONCE');

    $this->cart->add(sellable(1000));

    expect($this->validator->validate('ONCE', $this->cart->products(), 'buyer@example.com')->reason)
        ->toContain('already used')
        // Another buyer is unaffected.
        ->and($this->validator->validate('ONCE', $this->cart->products(), 'other@example.com')->valid)
        ->toBeTrue();
});

it('marks the order free when a code covers the whole total', function (): void {
    Coupon::factory()->percent(100)->create(['code' => 'ONTHEHOUSE']);
    $this->cart->add(sellable(1000));

    $order = app(PlaceOrder::class)(request(), 'buyer@example.com', false, 'ONTHEHOUSE');

    // PayPal rejects an order of 0.00, so this has to take the free path.
    expect($order->total_cents)->toBe(0)
        ->and($order->is_free)->toBeTrue();
});

it('previews a code without committing to it', function (): void {
    Coupon::factory()->percent(20)->create(['code' => 'PEEK']);
    $this->cart->add(sellable(1000));

    $this->postJson(route('checkout.coupon'), ['coupon' => 'PEEK'])
        ->assertOk()
        ->assertJson(['valid' => true, 'discount' => '$2.00', 'total' => '$8.00', 'totalCents' => 800]);

    // A preview reserves nothing: only placing an order consumes a use.
    expect(Coupon::firstOrFail()->used_count)->toBe(0)
        ->and(CouponUsage::count())->toBe(0);
});

it('explains a rejected code to the page', function (): void {
    Coupon::factory()->expired()->create(['code' => 'STALE']);
    $this->cart->add(sellable(1000));

    $this->postJson(route('checkout.coupon'), ['coupon' => 'STALE'])
        ->assertStatus(422)
        ->assertJson(['valid' => false])
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'expired'));
});

it('prices the order from the catalog even when the browser sends a code', function (): void {
    // The browser sends product ids and a coupon code; nothing about money.
    Coupon::factory()->fixed(100)->create(['code' => 'DOLLAR']);
    $this->cart->add(sellable(1000));

    $order = app(PlaceOrder::class)(request(), 'buyer@example.com', false, 'DOLLAR');

    expect($order->total_cents)->toBe(900);
});

it('gives a reserved use back when an abandoned order is cancelled', function (): void {
    $coupon = Coupon::factory()->percent(10)->create(['code' => 'LIMITED', 'usage_limit' => 1]);
    $this->cart->add(sellable(1000));

    $order = app(PlaceOrder::class)(request(), 'buyer@example.com', false, 'LIMITED');
    expect($coupon->refresh()->used_count)->toBe(1);

    app(CouponLedger::class)->release($order);

    // Otherwise a limited coupon is slowly eaten by carts nobody paid for.
    expect($coupon->refresh()->used_count)->toBe(0)
        ->and(CouponUsage::count())->toBe(0);
});
