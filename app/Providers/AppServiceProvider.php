<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RecordEmailEvents;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SettingsRepository::class,
            fn ($app) => new SettingsRepository($app['cache.store']),
        );
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);

        // Laravel announces both outcomes already; until now nothing listened,
        // so a message SMTP rejected went into failed_jobs and nowhere a
        // person looks. See §15.
        Event::listen(MessageSent::class, [RecordEmailEvents::class, 'sent']);
        Event::listen(NotificationFailed::class, [RecordEmailEvents::class, 'failed']);

        if (! $this->app->isProduction()) {
            // Surface N+1 queries and slow requests in development, where they
            // are cheap to fix, rather than in production where they are not.
            DB::whenQueryingForLongerThan(500, function ($connection, $event): void {
                logger()->warning('Slow query', [
                    'sql' => $event->sql,
                    'time' => $event->time,
                ]);
            });
        }
    }
}
