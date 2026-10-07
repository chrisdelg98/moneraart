<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Downloads\IssueDownloadGrants;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\DownloadLinksReady;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paid becomes completed: grants issued, email sent.
 *
 * Grants are created synchronously — a customer who has paid must not wait on
 * a queue worker — while the email is queued, because it only has to eventually
 * arrive. See §5.2.
 */
final class CompleteOrder
{
    public function __construct(private readonly IssueDownloadGrants $issue) {}

    public function __invoke(Order $order): Order
    {
        if ($order->status === OrderStatus::Completed) {
            return $order;
        }

        if (! $order->status->canTransitionTo(OrderStatus::Completed)) {
            throw new \RuntimeException(
                "Cannot complete an order that is {$order->status->value}."
            );
        }

        $grants = DB::transaction(function () use ($order) {
            $grants = ($this->issue)($order);

            $order->forceFill([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ])->save();

            return $grants;
        });

        $order->customer->notify(new DownloadLinksReady($order, $grants));

        Log::channel(config('logging.default'))->info('order.completed', [
            'order' => $order->number,
            'grants' => $grants->count(),
        ]);

        return $order->refresh();
    }
}
