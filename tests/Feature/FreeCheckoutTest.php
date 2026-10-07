<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\DownloadLinksReady;
use App\Services\Cart\CartService;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);
    $this->cart = app(CartService::class);
});

it('delivers a free product with no payment step', function (): void {
    $this->cart->add(sellable(0));

    $this->post(route('checkout.free'), ['email' => 'buyer@example.com', 'terms' => 1])
        ->assertRedirectContains('/order/');

    $order = Order::firstOrFail();

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->is_free)->toBeTrue()
        ->and($order->total_cents)->toBe(0)
        // No payment happened, so no payment row: a free download must never
        // show up in revenue.
        ->and(Payment::count())->toBe(0)
        ->and(DownloadGrant::count())->toBe(1)
        ->and($this->cart->isEmpty())->toBeTrue();

    Notification::assertSentTo($order->customer, DownloadLinksReady::class);
});

it('loads no payment script on a free checkout', function (): void {
    $this->cart->add(sellable(0));

    $this->get(route('checkout'))->assertOk()
        ->assertSee('Get your free art')
        ->assertDontSee('paypal.com/sdk/js', escape: false);
});

it('still requires accepted terms', function (): void {
    $this->cart->add(sellable(0));

    $this->post(route('checkout.free'), ['email' => 'buyer@example.com'])
        ->assertSessionHasErrors('terms');

    expect(Order::count())->toBe(0);
});

it('refuses the free path for a cart that costs money', function (): void {
    $this->cart->add(sellable(599));

    $this->post(route('checkout.free'), ['email' => 'buyer@example.com', 'terms' => 1])
        ->assertRedirect(route('checkout'));

    expect(Order::count())->toBe(0);
});

it('gives free links a shorter life than paid ones', function (): void {
    $this->cart->add(sellable(0));
    $this->post(route('checkout.free'), ['email' => 'buyer@example.com', 'terms' => 1]);

    expect(DownloadGrant::first()->expires_at->diffInHours(now(), absolute: true))
        ->toBeLessThanOrEqual(24);
});

it('caps how many free pieces one address can take in a day', function (): void {
    // Free files are the one surface a stranger can drain without paying.
    foreach (range(1, 6) as $i) {
        $this->cart->add(sellable(0));
        $this->post(route('checkout.free'), ['email' => 'same@example.com', 'terms' => 1]);
    }

    expect(Order::count())->toBe(5);

    $this->followingRedirects()
        ->post(route('checkout.free'), ['email' => 'same@example.com', 'terms' => 1]);

    expect(Order::count())->toBe(5);
});
