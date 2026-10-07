<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Without this the customer column is one query per row.
            ->modifyQueryUsing(fn ($query) => $query->with('customer'))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('email_domain')
                    ->label('Customer')
                    // The address itself is encrypted and only revealed on the
                    // order, with the reveal audited. The domain is enough to
                    // scan a list by. See §9.1 and §13.4.
                    ->getStateUsing(fn (Order $r): string => '•••@'.($r->customer->email_domain ?? '—'))
                    ->description(fn (Order $r): ?string => $r->customer->orders_count > 1
                        ? 'Returning customer'
                        : null),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->getStateUsing(fn (Order $r): string => $r->total()->format())
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('manual_review_reason')
                    ->label('Needs attention')
                    ->wrap()
                    ->color('danger')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Oldest first inside this filter: the longest-waiting customer
                // is the one to deal with first.
                Filter::make('needs_attention')
                    ->label('Needs attention')
                    ->query(fn ($query) => $query
                        ->where('status', OrderStatus::ManualReview)
                        ->reorder('created_at', 'asc'))
                    ->default(false),

                Filter::make('stuck')
                    ->label('Stuck at pending')
                    ->query(fn ($query) => $query
                        ->where('status', OrderStatus::Pending)
                        ->where('created_at', '<', now()->subMinutes(30))),

                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())
                        ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('They appear here the moment someone buys.');
    }
}
