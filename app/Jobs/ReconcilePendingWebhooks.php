<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-dispatches verified events whose job never ran.
 *
 * The controller writes the row before queueing, so a worker that crashed
 * between the two leaves a verified payment event nobody acted on. See §6.7.
 */
class ReconcilePendingWebhooks implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        WebhookEvent::query()
            ->where('status', 'received')
            ->where('received_at', '<', now()->subMinutes(5))
            ->orderBy('received_at')
            ->limit(200)
            ->get()
            ->each(fn (WebhookEvent $event) => ProcessPayPalWebhook::dispatch($event->getKey()));
    }
}
