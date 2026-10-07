<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
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
