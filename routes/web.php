<?php

declare(strict_types=1);

use App\Http\Controllers\Checkout\CouponController;
use App\Http\Controllers\Checkout\FreeCheckoutController;
use App\Http\Controllers\Checkout\PayPalCheckoutController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\RenewDownloadController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\LegalController;
use App\Http\Controllers\Storefront\OrderSuccessController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ShopController;
use Illuminate\Support\Facades\Route;

/*
 * The storefront.
 *
 * Locale prefixes (/en, /es) arrive with the bilingual pass — the schema is
 * already in place for them. Until then the store runs at the default locale.
 * See §4.7.3.
 */

Route::get('/', HomeController::class)->name('home');
Route::get('/shop', ShopController::class)->name('shop');
Route::get('/art/{slug}', ProductController::class)->name('product');

Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/{product:uuid}', [CartController::class, 'add'])->name('cart.add');
Route::delete('/cart/{product:uuid}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');

// Tighter than the rest of checkout: this endpoint answers "does this code
// exist?", which is exactly what a brute-force search needs. See §16.3.
Route::post('/checkout/coupon', CouponController::class)
    ->middleware('throttle:20,1')
    ->name('checkout.coupon');

Route::post('/checkout/paypal/create', [PayPalCheckoutController::class, 'create'])
    ->middleware('throttle:10,1')
    ->name('checkout.paypal.create');

Route::post('/checkout/paypal/capture', [PayPalCheckoutController::class, 'capture'])
    ->middleware('throttle:10,1')
    ->name('checkout.paypal.capture');

// PayPal needs somewhere to send a buyer who finishes or abandons the flow.
Route::view('/checkout/return', 'storefront.checkout-return')->name('checkout.return');
Route::get('/checkout/cancel', fn () => redirect()->route('cart'))->name('checkout.cancel');

// Signed and valid for 7 days, so closing the tab does not lose the download.
Route::get('/order/{order:uuid}', OrderSuccessController::class)
    ->middleware('signed')
    ->name('order.success');

// Narrow limit: enumeration is pointless against 256 bits, but we refuse to be
// a bandwidth amplifier. See §16.3.
Route::get('/d/{token}', DownloadController::class)
    ->middleware('throttle:20,1')
    ->name('download');

Route::post('/d/{grant:uuid}/renew', RenewDownloadController::class)
    ->middleware('throttle:3,60')
    ->name('download.renew');

Route::post('/checkout/free', FreeCheckoutController::class)
    ->middleware('throttle:10,60')
    ->name('checkout.free');

Route::get('/{slug}', LegalController::class)
    ->whereIn('slug', ['terms', 'privacy', 'refunds', 'how-we-work'])
    ->name('legal');
