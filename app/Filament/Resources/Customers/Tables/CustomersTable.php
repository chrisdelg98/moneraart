<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Support\BlindIndex;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_order_at', 'desc')
            ->columns([
                TextColumn::make('email_domain')
                    ->label('Customer')
                    // The address is encrypted and shown only on the detail
                    // page, behind an audited reveal. The domain is enough to
                    // recognise a row by. See §9.1 and §13.4.
                    ->getStateUsing(fn (Customer $r): string => '•••@'.($r->email_domain ?? '—'))
                    ->description(fn (Customer $r): string => 'Since '
                        .($r->first_order_at?->format('M Y') ?? $r->created_at->format('M Y'))),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('lifetime_value_cents')
                    ->label('Lifetime value')
                    ->alignEnd()
                    ->sortable()
                    ->getStateUsing(fn (Customer $r): string => Money::fromCents(
                        (int) $r->lifetime_value_cents,
                        (string) config('store.currency', 'USD'),
                    )->format()),

                TextColumn::make('last_order_at')
                    ->label('Last order')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),

                IconColumn::make('marketing_consent')
                    ->label('Emails')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Exact match only, and that is not a shortcoming to apologise
                // for: the index is an HMAC of the address, so a LIKE search
                // would mean storing it in clear. See §9.2.
                Filter::make('email')
                    ->schema([
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->helperText('The whole address. Encrypted records cannot be searched by part of one.'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['email'] ?? null),
                        fn (Builder $q) => $q->where('email_hash', BlindIndex::email((string) $data['email'])),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['email'] ?? null)
                        ? 'Email: '.$data['email']
                        : null),

                TernaryFilter::make('marketing_consent')->label('Accepts email'),

                Filter::make('returning')
                    ->label('Returning customers')
                    ->query(fn (Builder $query) => $query->where('orders_count', '>', 1)),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('A customer record is created by the first order, never by hand.');
    }
}
