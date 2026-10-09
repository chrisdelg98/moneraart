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

/**
 * The last ten orders, whatever became of them.
 *
 * Failed and cancelled ones belong here too: a run of failures is something to
 * notice, and a list that only shows successes hides exactly that. See §13.2.
 */
class RecentOrders extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            // The customer column is one query per row without this.
            ->query(Order::query()->with('customer')->latest('created_at')->limit(10))
            ->heading('Recent orders')
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->weight('medium'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),

                TextColumn::make('email_domain')
                    ->label('Customer')
                    // The address itself is encrypted and revealed only on the
                    // order, with the reveal audited. See §9.1 and §13.4.
                    ->getStateUsing(fn (Order $r): string => '•••@'.($r->customer->email_domain ?? '—')),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->alignEnd()
                    ->getStateUsing(fn (Order $r): string => $r->total()->format()),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('View')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('The first one will appear here the moment it is paid.');
    }
}
