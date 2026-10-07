<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Orders\MarkOrderPaid;
use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies a verified webhook event.
 *
 * Runs off the request cycle so the endpoint can acknowledge immediately, and
 * reaches the same MarkOrderPaid action the capture path does — so whichever
 * arrives first wins and the other is a no-op. See §6.8.
 */
class ProcessPayPalWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 120, 600];

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(MarkOrderPaid $markPaid): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if ($event === null || $event->status === 'processed') {
            return;
        }

        $event->increment('attempts');

        try {
            $handled = match ($event->event_type) {
                'PAYMENT.CAPTURE.COMPLETED' => $this->capture($event, $markPaid),
                default => false,
            };

            $event->update([
                'status' => $handled ? 'processed' : 'ignored',
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $event->update(['status' => 'failed', 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function capture(WebhookEvent $event, MarkOrderPaid $markPaid): bool
    {
        /** @var array<string, mixed> $resource */
        $resource = $event->payload['resource'] ?? [];

        // custom_id carries our order uuid, which is how an event finds its
        // order even when our own provider_order_id write failed.
        $uuid = $resource['custom_id'] ?? null;
        $order = is_string($uuid) ? Order::where('uuid', $uuid)->first() : null;

        if ($order === null) {
            Log::channel(config('logging.default'))->warning('paypal.webhook.unknown_order', [
                'event_id' => $event->event_id,
                'custom_id' => $uuid,
            ]);

            return false;
        }

        $markPaid($order, $resource, 'webhook');

        return true;
    }
}
