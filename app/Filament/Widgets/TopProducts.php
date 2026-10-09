<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\OrderItem;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What is actually selling this month.
 *
 * Counted from order lines rather than a products table, because the line
 * holds what was paid: a piece whose price changed last week still reports the
 * revenue it earned at the price it was sold for.
 */
class TopProducts extends Widget
{
    protected string $view = 'filament.widgets.top-products';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return self::rows()->isNotEmpty();
    }

    /** @return Collection<int, TopProduct> */
    public function getRows(): Collection
    {
        return self::rows();
    }

    /** @return Collection<int, TopProduct> */
    private static function rows(): Collection
    {
        $lines = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', Carbon::now()->startOfMonth())
                ->where('status', '!=', OrderStatus::Refunded))
            ->with('product.coverImage')
            ->get();

        return $lines
            // Grouped on the title snapshot, so a piece deleted since the sale
            // still appears under the name it was bought as.
            ->groupBy('title_snapshot')
            ->map(function (Collection $group, string $title): TopProduct {
                $product = $group->first()->product;

                return new TopProduct(
                    title: $title,
                    sales: $group->count(),
                    revenue: Money::fromCents(
                        (int) $group->sum('total_cents'),
                        (string) config('store.currency', 'USD'),
                    ),
                    cover: $product?->coverImage,
                    url: $product !== null
                        ? ProductResource::getUrl('edit', ['record' => $product])
                        : null,
                );
            })
            ->sortByDesc('sales')
            ->take(5)
            ->values();
    }
}
