<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\MarkOrderPaid;
use App\Actions\Orders\RefundOrder;
use App\Enums\OrderStatus;
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
                'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED' => $this->refund($event),
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

    /** A refund started in PayPal's dashboard still has to take access back. */
    private function refund(WebhookEvent $event): bool
    {
        /** @var array<string, mixed> $resource */
        $resource = $event->payload['resource'] ?? [];

        $captureId = (string) data_get($resource, 'links.0.href', '');
        $order = $this->orderFor($resource, $captureId);

        if ($order === null) {
            return false;
        }

        app(RefundOrder::class)->fromWebhook($order, $resource);

        return true;
    }

    /** @param array<string, mixed> $resource */
    private function orderFor(array $resource, string $captureIdHint): ?Order
    {
        $custom = $resource['custom_id'] ?? null;

        if (is_string($custom)) {
            return Order::where('uuid', $custom)->first();
        }

        // A refund resource names the capture it reverses, not our order.
        $captureId = (string) data_get($resource, 'links.1.href', $captureIdHint);
        preg_match('#/captures/([A-Z0-9]+)#i', $captureId, $m);

        return isset($m[1])
            ? Order::whereHas('payments', fn ($q) => $q->where('provider_capture_id', $m[1]))->first()
            : null;
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

        $outcome = $markPaid($order, $resource, 'webhook');

        // The customer may have closed the tab before the capture call
        // returned; this is the path that still delivers their files.
        if ($outcome->isSuccessful() && $outcome->order->status->canTransitionTo(
            OrderStatus::Completed
        )) {
            app(CompleteOrder::class)($outcome->order);
        }

        return true;
    }
}
