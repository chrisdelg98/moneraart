<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\DownloadLog;
use App\Models\Order;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * The six numbers worth seeing before anything else.
 *
 * Manual reviews is the one that matters most: every other tile reports what
 * has happened, and that one reports a customer who has paid and is waiting.
 * It is the only tile that turns red, so a glance is enough. See §13.2.
 */
class StoreOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $thisMonth = $this->revenueBetween($monthStart, $today->copy()->endOfDay());
        $lastMonth = $this->revenueBetween(
            $monthStart->copy()->subMonthNoOverflow(),
            $monthStart->copy()->subSecond(),
        );

        $reviews = Order::query()->where('status', OrderStatus::ManualReview)->count();
        $pending = Order::query()->where('status', OrderStatus::Pending)->count();

        return [
            Stat::make('Revenue today', $this->revenueBetween($today, $today->copy()->endOfDay())->format())
                ->description(Order::query()->whereDate('paid_at', $today)->count().' paid')
                ->color('gray'),

            Stat::make('Revenue this month', $thisMonth->format())
                ->description($this->delta($thisMonth, $lastMonth))
                ->descriptionIcon($thisMonth->cents >= $lastMonth->cents
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($thisMonth->cents >= $lastMonth->cents ? 'success' : 'danger')
                ->chart($this->last30Days())
                ->color($thisMonth->cents >= $lastMonth->cents ? 'success' : 'gray'),

            Stat::make('Orders today', (string) Order::query()->whereDate('created_at', $today)->count())
                ->description('Placed, however they ended')
                ->color('gray'),

            // Red whenever it is not zero. Nothing else on this page earns that.
            Stat::make('Manual reviews', (string) $reviews)
                ->description($reviews === 0 ? 'Nothing waiting' : 'Paid, waiting on you')
                ->descriptionIcon($reviews > 0 ? 'heroicon-m-exclamation-triangle' : null)
                ->color($reviews > 0 ? 'danger' : 'gray'),

            Stat::make('Pending payments', (string) $pending)
                ->description('At PayPal, or abandoned')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Downloads today', (string) DownloadLog::query()->whereDate('created_at', $today)->count())
                ->description('Files actually delivered')
                ->color('gray'),
        ];
    }

    /** Revenue is what was captured, so it counts paid_at and nothing else. */
    private function revenueBetween(Carbon $from, Carbon $to): Money
    {
        $cents = (int) Order::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            // A refunded order is money that came back; it was never revenue.
            ->where('status', '!=', OrderStatus::Refunded)
            ->sum('total_cents');

        return Money::fromCents($cents, (string) config('store.currency', 'USD'));
    }

    private function delta(Money $now, Money $before): string
    {
        if ($before->cents === 0) {
            return $now->cents === 0 ? 'No sales last month either' : 'First sales this month';
        }

        $percent = (int) round((($now->cents - $before->cents) / $before->cents) * 100);

        return sprintf('%+d%% on last month', $percent);
    }

    /**
     * Thirty daily totals for the sparkline, in one query rather than thirty.
     *
     * @return list<float>
     */
    private function last30Days(): array
    {
        $from = Carbon::today()->subDays(29);

        $byDay = Order::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->where('status', '!=', OrderStatus::Refunded)
            ->get(['paid_at', 'total_cents'])
            ->groupBy(fn (Order $o): string => $o->paid_at->toDateString())
            ->map(fn ($orders): int => (int) $orders->sum('total_cents'));

        // Floats, because the chart plots them: a day with no sales still
        // needs a point on the line, not a gap.
        return collect(range(0, 29))
            ->map(fn (int $i): float => (float) $byDay->get($from->copy()->addDays($i)->toDateString(), 0))
            ->all();
    }
}
