<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders that are paid and not delivered.
 *
 * The widget hides itself when the queue is empty rather than sitting there
 * saying "nothing to do" — an empty table trains the eye to skip the place
 * where the urgent thing will one day appear. See §13.2 and §13.5.
 */
class NeedsAttention extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return self::queue()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::queue()->with('customer'))
            ->heading('Needs your attention')
            ->description('These customers have paid. Their files are waiting on a decision.')
            ->defaultSort('paid_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->weight('medium'),

                TextColumn::make('manual_review_reason')
                    ->label('Why')
                    ->wrap()
                    ->color('danger'),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->alignEnd()
                    ->getStateUsing(fn (Order $r): string => $r->total()->format()),

                TextColumn::make('paid_at')
                    ->label('Waiting')
                    // How long someone has been waiting is the number that
                    // decides which of these to open first.
                    ->since()
                    ->description(fn (Order $r): ?string => $r->paid_at?->format('j M, H:i')),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Review')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /** @return Builder<Order> */
    private static function queue(): Builder
    {
        return Order::query()->where('status', OrderStatus::ManualReview);
    }
}
