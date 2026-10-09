<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * What this customer has bought.
 *
 * Read-only: an order is a record of what happened, and the things worth doing
 * to one are on the order itself, where the audit trail is. See §13.4.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->weight('medium'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->alignEnd()
                    ->getStateUsing(fn (Order $r): string => $r->total()->format()),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('View')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No orders')
            ->emptyStateDescription('This address has been seen, but nothing has been bought with it.');
    }
}
