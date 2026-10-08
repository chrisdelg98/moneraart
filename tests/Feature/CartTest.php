<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Cart\CartService;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);
    $this->cart = app(CartService::class);
});

it('adds a product and reports the count', function (): void {
    $product = sellable();

    $this->post(route('cart.add', $product->uuid))->assertRedirect(route('cart'));

    expect($this->cart->count())->toBe(1)
        ->and($this->cart->has($product))->toBeTrue();
});

it('sets a readable cookie so the badge needs no request', function (): void {
    // assertPlainCookie, not assertCookie: the latter decrypts, and would pass
    // just as happily against a value no browser script can parse. The badge
    // reads this with a regex. See §7.1.
    $this->post(route('cart.add', sellable()->uuid))
        ->assertPlainCookie('cart_count', '1')
        ->assertCookieNotExpired('cart_count');
});

it('refuses a product that has no files to deliver', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();

    $this->from(route('product', $product->translate('en')->slug))
        ->post(route('cart.add', $product->uuid))
        ->assertRedirect(route('product', $product->translate('en')->slug))
        ->assertSessionHas('notice.isError', true);

    expect($this->cart->count())->toBe(0);
});

it('refuses an unpublished product', function (): void {
    $product = sellable();
    $product->update(['status' => ProductStatus::Draft]);

    $this->post(route('cart.add', $product->uuid));

    expect($this->cart->count())->toBe(0);
});

it('refuses the same artwork twice', function (): void {
    $product = sellable();

    $this->post(route('cart.add', $product->uuid));
    $this->post(route('cart.add', $product->uuid))
        ->assertSessionHas('notice.text', 'That artwork is already in your cart.')
        ->assertSessionHas('notice.url', route('cart'));

    expect($this->cart->count())->toBe(1);
});

it('removes a product', function (): void {
    $product = sellable();
    $this->post(route('cart.add', $product->uuid));

    $this->delete(route('cart.remove', $product->uuid))->assertRedirect(route('cart'));

    expect($this->cart->count())->toBe(0);
});

it('totals the live prices, not anything held in the session', function (): void {
    $a = sellable(599);
    $b = sellable(1299);

    $this->cart->add($a);
    $this->cart->add($b);

    expect($this->cart->subtotal()->toDecimalString())->toBe('18.98');

    // The catalog changes; the cart must follow it, not its own copy.
    $a->update(['price_cents' => 399]);

    expect($this->cart->subtotal()->toDecimalString())->toBe('16.98');
});

it('uses the sale price while a sale is running', function (): void {
    $product = sellable(1000);
    $product->update([
        'sale_price_cents' => 599,
        'sale_starts_at' => now()->subDay(),
        'sale_ends_at' => now()->addDay(),
    ]);

    $this->cart->add($product);

    expect($this->cart->subtotal()->toDecimalString())->toBe('5.99');
});

it('drops a product that stopped being sellable after it was added', function (): void {
    $product = sellable();
    $this->cart->add($product);

    $product->update(['status' => ProductStatus::Archived]);

    // It must not reach checkout as something that cannot be sold.
    expect($this->cart->products())->toBeEmpty()
        ->and($this->cart->subtotal()->isZero())->toBeTrue();
});

it('shows the cart page empty and filled', function (): void {
    $this->get(route('cart'))->assertOk()->assertSee('Your cart is empty');

    $product = sellable();
    $this->cart->add($product);

    $this->get(route('cart'))->assertOk()
        ->assertSee($product->title('en'))
        ->assertDontSee('Your cart is empty');
});

it('sends an empty cart back rather than showing a checkout with nothing in it', function (): void {
    $this->get(route('checkout'))->assertRedirect(route('cart'));
});

it('shows checkout with the order summary once there is something to buy', function (): void {
    $product = sellable(1299);
    $this->cart->add($product);

    $this->get(route('checkout'))->assertOk()
        ->assertSee($product->title('en'))
        ->assertSee('$12.99')
        // Consent is never pre-ticked. See §7.7.1.
        ->assertSee('name="terms"', escape: false)
        ->assertDontSee('checked', escape: false);
});

it('caps the cart rather than letting it grow without limit', function (): void {
    foreach (range(1, 31) as $i) {
        $this->cart->add(sellable());
    }

    expect($this->cart->count())->toBe(30);
});

it('tells the visitor an artwork is already in the cart, with a way to see it', function (): void {
    $product = sellable();
    $shop = route('shop');

    $this->post(route('cart.add', $product->uuid));

    // The refusal stays on the page it happened on, and the toast carries the
    // link: without one, "already in your cart" leaves nowhere to go.
    $this->from($shop)
        ->post(route('cart.add', $product->uuid))
        ->assertRedirect($shop);

    $this->followingRedirects()
        ->from($shop)
        ->post(route('cart.add', $product->uuid))
        ->assertOk()
        ->assertSee('already in your cart')
        ->assertSee('data-toast', escape: false)
        ->assertSee(route('cart'), escape: false);
});

it('repairs a badge cookie that disagrees with the cart', function (): void {
    // The cookie outliving its value is the ordinary case: it expires, or was
    // written under a scheme the badge script can no longer read, while the
    // session still holds the cart. Every page corrects it.
    //
    // The cart is seeded through the session rather than by posting to the
    // cart: CookieJar is a singleton for the whole test, so a queued cookie
    // from an earlier request rides along on this response and the assertion
    // passes whether the middleware exists or not.
    $this->withSession(['cart' => [sellable()->getKey()]])
        ->withUnencryptedCookie('cart_count', '7')
        ->get(route('shop'))
        ->assertPlainCookie('cart_count', '1');
});

it('leaves the badge cookie alone when it already agrees', function (): void {
    $this->withUnencryptedCookie('cart_count', '0')
        ->get(route('shop'))
        ->assertCookieMissing('cart_count');
});
