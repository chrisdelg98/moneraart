<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                Section::make('Customer')
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->columns(2)
                    ->schema([
                        TextEntry::make('email_domain')
                            ->label('Email')
                            // Never rendered in clear on load. The Reveal action
                            // in the header is the only way, and it is audited.
                            ->state(fn (Customer $r): string => '•••@'.($r->email_domain ?? '—'))
                            ->helperText('Encrypted. Use Reveal above to read it.'),

                        TextEntry::make('locale')->label('Language')->badge(),

                        IconEntry::make('marketing_consent')
                            ->label('Accepts marketing email')
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label('First seen')
                            ->dateTime('j M Y, H:i'),
                    ]),

                Section::make('Trading')
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        TextEntry::make('orders_count')
                            ->label('Paid orders')
                            ->helperText('Refunded orders are not counted.'),

                        TextEntry::make('lifetime_value_cents')
                            ->label('Lifetime value')
                            ->weight('medium')
                            ->state(fn (Customer $r): string => Money::fromCents(
                                (int) $r->lifetime_value_cents,
                                (string) config('store.currency', 'USD'),
                            )->format()),

                        TextEntry::make('first_order_at')
                            ->label('First order')
                            ->dateTime('j M Y')
                            ->placeholder('Never'),

                        TextEntry::make('last_order_at')
                            ->label('Last order')
                            ->dateTime('j M Y')
                            ->placeholder('Never'),
                    ]),
            ]),
        ]);
    }
}
