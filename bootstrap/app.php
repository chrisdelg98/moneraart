<?php

declare(strict_types=1);

use App\Http\Middleware\SyncCartCountCookie;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Outside the web group: no CSRF token, no session cookie.
        then: function (): void {
            Route::middleware('api')
                ->withoutMiddleware(['throttle:api'])
                ->group(__DIR__.'/../routes/webhooks.php');
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The cart badge is painted from this cookie in the browser, so it has
        // to survive as plain digits. It holds a count and nothing else — no
        // identifier, no session state, nothing worth encrypting. See §7.1.
        $middleware->encryptCookies(except: ['cart_count']);

        // Last in the group, so the session is started and the cart readable.
        $middleware->web(append: [SyncCartCountCookie::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
