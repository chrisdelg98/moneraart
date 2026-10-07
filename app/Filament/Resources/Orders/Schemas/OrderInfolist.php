<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                Group::make()->columnSpan(['default' => 3, 'lg' => 2])->schema([
                    self::whatWasBought(),
                    self::downloads(),
                ]),

                Group::make()->columnSpan(['default' => 3, 'lg' => 1])->schema([
                    self::summary(),
                    self::payment(),
                ]),
            ]),
        ]);
    }

    private static function whatWasBought(): Section
    {
        return Section::make('What was bought')->schema([
            RepeatableEntry::make('items')
                ->hiddenLabel()
                ->schema([
                    TextEntry::make('title_snapshot')->hiddenLabel()->weight('medium'),
                    TextEntry::make('total_cents')
                        ->hiddenLabel()
                        ->getStateUsing(fn (OrderItem $record): string => $record->total()->format()),
                    TextEntry::make('file_manifest')
                        ->label('Files')
                        ->getStateUsing(fn (OrderItem $record): string => count($record->file_manifest).' files'),
                ])
                ->columns(3),
        ]);
    }

    private static function downloads(): Section
    {
        return Section::make('Downloads')
            ->description('Counts and expiry per file. These are what the customer holds.')
            ->schema([
                RepeatableEntry::make('downloadGrants')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('file.display_name')->label('File'),
                        TextEntry::make('download_count')
                            ->label('Used')
                            ->getStateUsing(fn (DownloadGrant $record): string => "{$record->download_count} of {$record->max_downloads}"),
                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->since()
                            ->badge()
                            ->color(fn (DownloadGrant $record): string => $record->isUsable() ? 'success' : 'danger'),
                    ])
                    ->columns(3)
                    ->placeholder('No links issued yet.'),
            ]);
    }

    private static function summary(): Section
    {
        return Section::make('Order')->schema([
            TextEntry::make('number')->label('Number')->copyable(),

            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                ->color(fn (OrderStatus $state): string => $state->color()),

            TextEntry::make('manual_review_reason')
                ->label('Why it needs attention')
                ->color('danger')
                ->visible(fn (Order $record): bool => filled($record->manual_review_reason)),

            TextEntry::make('total_cents')
                ->label('Total')
                ->getStateUsing(fn (Order $record): string => $record->total()->format()),

            TextEntry::make('customer.email_domain')
                ->label('Customer')
                // Never rendered in full here — revealing it is a deliberate,
                // audited action. See §9.1.
                ->getStateUsing(fn (Order $record): string => '•••@'.($record->customer->email_domain ?? '—')),

            TextEntry::make('created_at')->label('Placed')->dateTime(),
            TextEntry::make('completed_at')->label('Delivered')->dateTime()->placeholder('—'),

            TextEntry::make('terms_version')
                ->label('Terms accepted')
                // Which revision this buyer agreed to, which is the only form
                // of the question that matters in a dispute. See §7.7.2.
                ->getStateUsing(fn (Order $record): string => $record->terms_version !== null
                    ? "v{$record->terms_version} on ".$record->terms_accepted_at?->format('j M Y')
                    : '—'),
        ]);
    }

    private static function payment(): Section
    {
        return Section::make('Payment')->schema([
            TextEntry::make('payment.provider_capture_id')
                ->label('Capture ID')
                ->copyable()
                ->placeholder('Not captured'),

            TextEntry::make('payment.status')->label('Status')->badge()->placeholder('—'),

            TextEntry::make('payment.fee_cents')
                ->label('Fee')
                ->getStateUsing(fn (Order $record): string => $record->payment?->fee_cents !== null
                    ? Money::fromCents($record->payment->fee_cents, $record->currency)->format()
                    : '—'),

            TextEntry::make('payment.net_cents')
                ->label('You received')
                ->getStateUsing(fn (Order $record): string => $record->payment?->net_cents !== null
                    ? Money::fromCents($record->payment->net_cents, $record->currency)->format()
                    : '—'),

            TextEntry::make('payment.verified_at')->label('Verified')->dateTime()->placeholder('—'),
        ]);
    }
}
