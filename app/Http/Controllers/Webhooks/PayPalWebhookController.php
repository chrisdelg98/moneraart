<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Jobs\ProcessPayPalWebhook;
use App\Models\WebhookEvent;
use App\Services\PayPal\PayPalWebhookService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives PayPal notifications.
 *
 * No CSRF, no session, no auth — PayPal has none of those. Rate limited, and
 * everything else is the signature's job. See §6.7.
 */
class PayPalWebhookController
{
    public function __invoke(Request $request, PayPalWebhookService $webhooks): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $eventId = $payload['id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            return response()->noContent(400);
        }

        // Verified before anything from the payload reaches business logic.
        if (! $webhooks->verify($request)) {
            Log::channel(config('logging.default'))->warning('paypal.webhook.signature_failed', [
                'event_id' => $eventId,
            ]);

            // 403, not 200: PayPal retries non-2xx. Returning 200 here would
            // permanently discard real payment events while the webhook id is
            // briefly wrong.
            return response()->noContent(403);
        }

        try {
            // The unique index is the lock. A duplicate insert means we already
            // have this event.
            $event = WebhookEvent::create([
                'provider' => 'paypal',
                'event_id' => $eventId,
                'event_type' => (string) ($payload['event_type'] ?? 'unknown'),
                'resource_type' => $payload['resource_type'] ?? null,
                'resource_id' => data_get($payload, 'resource.id'),
                'signature_verified' => true,
                'payload' => $payload,
                'status' => 'received',
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->noContent(200);
        }

        // Acknowledge in milliseconds; PayPal times out slow endpoints and
        // marks them unhealthy. The row exists before the job runs, so a worker
        // crash loses nothing.
        ProcessPayPalWebhook::dispatch($event->getKey());

        return response()->noContent(200);
    }
}
