<?php

declare(strict_types=1);

/*
 * Environment fallbacks only. Values set in the admin UI take precedence —
 * see docs/implementation_plan.md §3.2 and §3.4.
 *
 * Sandbox and live credentials live under separate keys so that flipping the
 * mode toggle never destroys the other environment's keys, and a mis-set
 * toggle can never reach the wrong API with the wrong secret.
 */
return [
    'mode' => env('PAYPAL_MODE', 'sandbox'),

    'sandbox' => [
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID'),
        'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_SANDBOX_WEBHOOK_ID'),
    ],

    'live' => [
        'client_id' => env('PAYPAL_LIVE_CLIENT_ID'),
        'client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_LIVE_WEBHOOK_ID'),
    ],

    'timeout' => (int) env('PAYPAL_TIMEOUT', 15),
    'retries' => (int) env('PAYPAL_RETRIES', 3),
    'brand_name' => env('PAYPAL_BRAND_NAME'),
];
