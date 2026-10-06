<?php

declare(strict_types=1);

/*
 * Environment fallbacks only. The admin UI takes precedence (§3.2).
 */
return [
    'name' => env('APP_NAME', 'Monera Art'),
    'support_email' => env('STORE_SUPPORT_EMAIL'),
    'currency' => env('STORE_CURRENCY', 'USD'),
    'default_locale' => env('STORE_DEFAULT_LOCALE', 'en'),
    'locales' => ['en', 'es'],

    'download' => [
        'expiry_hours' => (int) env('DOWNLOAD_EXPIRY_HOURS', 72),
        'free_expiry_hours' => (int) env('DOWNLOAD_FREE_EXPIRY_HOURS', 24),
        'max_downloads' => (int) env('DOWNLOAD_MAX_DOWNLOADS', 5),
        'presigned_ttl_seconds' => (int) env('DOWNLOAD_PRESIGNED_TTL', 60),
    ],

    'storage' => [
        'driver' => env('STORE_STORAGE_DRIVER', 'local'),
        'r2' => [
            'account_id' => env('R2_ACCOUNT_ID'),
            'access_key_id' => env('R2_ACCESS_KEY_ID'),
            'secret_access_key' => env('R2_SECRET_ACCESS_KEY'),
            'bucket' => env('R2_BUCKET'),
        ],
    ],

    'pricing' => [
        // Fixed per-transaction fees dominate below this. See §6.12 and §14.1.
        'minimum_price_cents' => (int) env('STORE_MINIMUM_PRICE_CENTS', 199),
    ],

    'admin_path' => env('ADMIN_PATH', 'admin'),
];
