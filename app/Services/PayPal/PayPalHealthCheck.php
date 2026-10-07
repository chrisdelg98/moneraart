<?php

declare(strict_types=1);

namespace App\Services\PayPal;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Settings\SettingsRepository;
use Throwable;

/**
 * What the payments settings page shows under Status.
 *
 * Each check is something the owner cannot verify by looking at the form, and
 * each failure says what to do rather than that something is wrong. See §6.10.
 */
final class PayPalHealthCheck
{
    public function __construct(
        private readonly PayPalClient $client,
        private readonly SettingsRepository $settings,
    ) {}

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function run(): array
    {
        if (! $this->client->isConfigured()) {
            return [[
                'label' => 'Credentials',
                'ok' => false,
                'detail' => 'Not set yet. Paste your client ID and secret above.',
            ]];
        }

        $checks = [];
        $authenticated = false;

        try {
            $this->client->token();
            $authenticated = true;
            $checks[] = [
                'label' => 'Credentials valid',
                'ok' => true,
                'detail' => ucfirst($this->client->mode()->value).' mode',
            ];
        } catch (Throwable $e) {
            $checks[] = ['label' => 'Credentials', 'ok' => false, 'detail' => $e->getMessage()];
        }

        $webhookId = $this->client->webhookId();
        $checks[] = [
            'label' => 'Webhook registered',
            'ok' => $webhookId !== null,
            'detail' => $webhookId !== null
                ? 'ID '.substr($webhookId, 0, 12).'…'
                : 'Not registered. Press “Save and enable payments”.',
        ];

        $lastEvent = WebhookEvent::query()->where('provider', 'paypal')->latest('received_at')->first();
        $recentOrders = Order::query()->where('created_at', '>=', now()->subDay())->count();

        $checks[] = [
            'label' => 'Last webhook received',
            // Only a problem if orders happened and nothing arrived — a quiet
            // store with no webhooks is just a quiet store.
            'ok' => $lastEvent !== null || $recentOrders === 0,
            'detail' => $lastEvent !== null
                ? $lastEvent->received_at->diffForHumans()
                : ($recentOrders > 0
                    ? "None in 24h, but {$recentOrders} orders were placed. Re-register the webhook."
                    : 'None yet — expected until your first sale.'),
        ];

        $stuck = Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();

        if ($stuck > 0) {
            $checks[] = [
                'label' => 'Orders stuck at pending',
                'ok' => false,
                'detail' => "{$stuck} older than 30 minutes. Usually a missing webhook.",
            ];
        }

        $review = Order::query()->where('status', 'manual_review')->count();

        if ($review > 0) {
            $checks[] = [
                'label' => 'Waiting on you',
                'ok' => false,
                'detail' => "{$review} ".($review === 1 ? 'order needs' : 'orders need').' manual review.',
            ];
        }

        if ($authenticated && $this->settings->get('paypal.enabled') !== true) {
            $checks[] = [
                'label' => 'Payments',
                'ok' => false,
                'detail' => 'Credentials work, but checkout is still disabled.',
            ];
        }

        return $checks;
    }
}
