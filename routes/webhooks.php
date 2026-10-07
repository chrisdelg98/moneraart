<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Provider callbacks. No CSRF, no session, no auth — the signature is the
 * authentication. See §6.7.
 */
Route::post('/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.paypal');
