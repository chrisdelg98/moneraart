<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Jobs\ReconcileStuckOrders;
use App\Models\Order;
use App\Models\WebhookEvent;
use Filament\Widgets\Widget;

/**
 * Says out loud when the scheduler has stopped.
 *
 * ReconcileStuckOrders and ReconcilePendingWebhooks are what recover a customer
 * whose capture call and webhook both failed — they have paid and received
 * nothing. A cron that was never installed, or a queue worker that died, breaks
 * exactly that and breaks it silently: the admin looks identical either way.
 *
 * Nothing here is a heartbeat the jobs write. It is the symptom itself, read
 * straight from the data: work that should have been cleared and was not. A
 * heartbeat can be stale for reasons of its own, and a green one proves only
 * that something wrote a timestamp.
 */
class SchedulerHealth extends Widget
{
    protected string $view = 'filament.widgets.scheduler-health';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return self::stalePayments() > 0 || self::stuckOrders() > 0;
    }

    /** @return array<int, array{title: string, body: string}> */
    public function getFindings(): array
    {
        $findings = [];

        if (($events = self::stalePayments()) > 0) {
            $findings[] = [
                'title' => $events.' verified '.str('payment')->plural($events).' not applied',
                'body' => 'PayPal confirmed these and nothing acted on them. Each one is a customer '
                    .'who has paid. Check that the queue worker is running.',
            ];
        }

        if (($orders = self::stuckOrders()) > 0) {
            $findings[] = [
                'title' => $orders.' '.str('order')->plural($orders).' left pending past the cut-off',
                'body' => 'Orders older than '.ReconcileStuckOrders::ABANDON_AFTER_HOURS
                    .' hours should have been reconciled against PayPal and closed. '
                    .'Check that the scheduler is running: php artisan schedule:work.',
            ];
        }

        return $findings;
    }

    /**
     * Webhook events whose signature we verified and whose job never ran.
     *
     * ReconcilePendingWebhooks re-dispatches these every five minutes, so one
     * older than half an hour means that job is not running either.
     */
    private static function stalePayments(): int
    {
        return WebhookEvent::query()
            ->where('signature_verified', true)
            ->whereNull('processed_at')
            ->where('received_at', '<', now()->subMinutes(ReconcileStuckOrders::STUCK_AFTER_MINUTES))
            ->count();
    }

    /**
     * Pending orders the reconciler should have resolved and has not.
     *
     * Doubling the abandon window keeps a brief outage, or a job that has not
     * come round yet, from raising an alarm.
     */
    private static function stuckOrders(): int
    {
        return Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subHours(ReconcileStuckOrders::ABANDON_AFTER_HOURS * 2))
            ->count();
    }
}
