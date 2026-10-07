<?php

declare(strict_types=1);

namespace App\Services\PayPal;

use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook signature verification and auto-provisioning.
 *
 * Registering the webhook is the step WooCommerce makes the owner do by hand in
 * PayPal's developer dashboard, and skipping it is the single most common cause
 * of orders stuck at pending. See §6.6.
 */
final class PayPalWebhookService
{
    private const EVENTS = [
        'CHECKOUT.ORDER.APPROVED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.REFUNDED',
        'PAYMENT.CAPTURE.REVERSED',
        'CUSTOMER.DISPUTE.CREATED',
    ];

    public function __construct(
        private readonly PayPalClient $client,
        private readonly SettingsRepository $settings,
    ) {}

    public function verify(Request $request): bool
    {
        $webhookId = $this->client->webhookId();

        if ($webhookId === null) {
            Log::channel(config('logging.default'))->warning('paypal.webhook.no_id_configured');

            return false;
        }

        $certUrl = (string) $request->header('paypal-cert-url', '');

        // Validate the certificate host before handing the URL to anything.
        // An attacker controlling cert_url could otherwise point certificate
        // fetching wherever they like.
        if (! $this->isPayPalCertUrl($certUrl)) {
            Log::channel(config('logging.default'))->warning('paypal.webhook.bad_cert_url', ['url' => $certUrl]);

            return false;
        }

        try {
            $response = $this->client->request('POST', '/v1/notifications/verify-webhook-signature', [
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'cert_url' => $certUrl,
                'auth_algo' => $request->header('paypal-auth-algo'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'webhook_id' => $webhookId,
                // The decoded body, not a re-encoded one: signature checks are
                // byte-sensitive and json_encode(json_decode($x)) !== $x.
                'webhook_event' => $request->json()->all(),
            ]);
        } catch (Throwable $e) {
            Log::channel(config('logging.default'))->error('paypal.webhook.verify_failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        return ($response['verification_status'] ?? '') === 'SUCCESS';
    }

    /** Registers or repairs the webhook. The owner never copies an id. */
    public function provision(): WebhookProvisionResult
    {
        if (! $this->client->isConfigured()) {
            return WebhookProvisionResult::skipped('Add your PayPal client ID and secret first.');
        }

        $url = route('webhooks.paypal');

        if (! $this->isPubliclyReachable($url)) {
            return WebhookProvisionResult::skipped(
                'Your site is not reachable from the internet yet, so PayPal cannot deliver '
                .'notifications. Payments will still work, but orders may need manual approval. '
                .'Run this again once the site is live.'
            );
        }

        try {
            /** @var array<int, array<string, mixed>> $existing */
            $existing = $this->client->request('GET', '/v1/notifications/webhooks')['webhooks'] ?? [];

            foreach ($existing as $webhook) {
                if (($webhook['url'] ?? null) === $url) {
                    return $this->syncEvents($webhook);
                }
            }

            $created = $this->client->request('POST', '/v1/notifications/webhooks', [
                'url' => $url,
                'event_types' => array_map(fn (string $name): array => ['name' => $name], self::EVENTS),
            ]);

            $this->storeId((string) ($created['id'] ?? ''));

            return WebhookProvisionResult::created((string) ($created['id'] ?? ''));
        } catch (Throwable $e) {
            return WebhookProvisionResult::failed($e->getMessage());
        }
    }

    /** @param array<string, mixed> $webhook */
    private function syncEvents(array $webhook): WebhookProvisionResult
    {
        $id = (string) ($webhook['id'] ?? '');
        $this->storeId($id);

        /** @var list<array{name?: string}> $types */
        $types = $webhook['event_types'] ?? [];

        $current = array_values(array_filter(array_map(
            fn (array $type): ?string => $type['name'] ?? null,
            $types,
        )));
        sort($current);

        $wanted = self::EVENTS;
        sort($wanted);

        if ($current === $wanted) {
            return WebhookProvisionResult::alreadyCorrect($id);
        }

        // A webhook whose event list drifted delivers some events and silently
        // drops others — worse than none, because it looks like it works.
        $this->client->patchOperations("/v1/notifications/webhooks/{$id}", [[
            'op' => 'replace',
            'path' => '/event_types',
            'value' => array_map(fn (string $name): array => ['name' => $name], self::EVENTS),
        ]]);

        return WebhookProvisionResult::repaired($id);
    }

    private function storeId(string $id): void
    {
        if ($id !== '') {
            $this->settings->set($this->client->mode()->settingKey('webhook_id'), $id);
        }
    }

    private function isPayPalCertUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && (str_ends_with($host, '.paypal.com') || $host === 'paypal.com');
    }

    private function isPubliclyReachable(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        // PayPal cannot deliver to a host it cannot resolve. A dot is the
        // cheapest proxy for "this is not localhost".
        return str_contains($host, '.')
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with($host, '.localhost')
            && ! str_ends_with($host, '.test')
            && ! preg_match('/^(10|127|192\.168)\./', $host);
    }
}
