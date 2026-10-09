<?php

declare(strict_types=1);

namespace App\Filament\Resources\Downloads\Tables;

use App\Actions\Downloads\AdjustDownloadGrant;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DownloadGrant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every link that has been handed out, and what can still be done to it.
 *
 * The order screen reissues a whole set; this is the one place a single
 * file's link can be stopped, pushed out or replaced. See §8.5.
 */
class DownloadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Three relations on every row, each a query per row without
            // this: the order, its customer, and the file being delivered.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.customer', 'file']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (DownloadGrant $r): string => '•••@'
                        .($r->order->customer->email_domain ?? '—')),

                TextColumn::make('file.display_name')
                    ->label('File')
                    ->wrap()
                    ->placeholder('Deleted'),

                TextColumn::make('download_count')
                    ->label('Used')
                    ->alignEnd()
                    ->getStateUsing(fn (DownloadGrant $r): string => "{$r->download_count} / {$r->max_downloads}")
                    ->color(fn (DownloadGrant $r): string => $r->isExhausted() ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (DownloadGrant $r): string => self::status($r))
                    ->color(fn (DownloadGrant $r): string => $r->isUsable() ? 'success' : 'gray'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->since()
                    ->description(fn (DownloadGrant $r): string => $r->expires_at->format('j M, H:i'))
                    ->sortable(),

                TextColumn::make('last_downloaded_at')
                    ->label('Last used')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('usable')
                    ->label('Still usable')
                    ->query(fn (Builder $query) => $query
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                        ->whereColumn('download_count', '<', 'max_downloads')),

                Filter::make('revoked')
                    ->label('Revoked')
                    ->query(fn (Builder $query) => $query->whereNotNull('revoked_at')),

                Filter::make('exhausted')
                    ->label('Out of downloads')
                    ->query(fn (Builder $query) => $query->whereColumn('download_count', '>=', 'max_downloads')),
            ])
            ->recordActions([
                self::extendAction(),
                self::regenerateAction(),
                self::revokeAction(),
                self::restoreAction(),

                Action::make('order')
                    ->label('Order')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (DownloadGrant $r): string => OrderResource::getUrl('view', ['record' => $r->order])),
            ])
            ->emptyStateHeading('No downloads issued yet')
            ->emptyStateDescription('A link appears here the moment an order is paid for.');
    }

    private static function extendAction(): Action
    {
        return Action::make('extend')
            ->label('Extend')
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->schema([
                Select::make('hours')
                    ->label('Give them')
                    ->options([24 => '24 hours', 72 => '3 days', 168 => '7 days', 720 => '30 days'])
                    ->default(72)
                    ->required(),
            ])
            ->action(function (DownloadGrant $record, array $data): void {
                app(AdjustDownloadGrant::class)->extend($record, (int) $data['hours']);

                Notification::make()->title('Link extended')
                    ->body('Now valid until '.$record->refresh()->expires_at->format('j M, H:i').'.')
                    ->success()->send();
            });
    }

    private static function regenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label('Regenerate')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Replace this link?')
            // Said plainly, because it cannot be undone: the old token exists
            // nowhere but the customer's inbox.
            ->modalDescription('The old link stops working at once and the new one is emailed to the customer.')
            ->action(function (DownloadGrant $record): void {
                app(AdjustDownloadGrant::class)->regenerate($record);

                Notification::make()->title('New link sent')
                    ->body('The counter is back to zero and the previous link no longer works.')
                    ->success()->send();
            });
    }

    private static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')
            ->icon('heroicon-m-no-symbol')
            ->color('danger')
            ->visible(fn (DownloadGrant $r): bool => ! $r->isRevoked())
            ->schema([
                Textarea::make('reason')
                    ->label('Why')
                    ->required()
                    ->rows(2)
                    // Required, because a revoked link with no reason is a
                    // question somebody has to answer months later.
                    ->helperText('Recorded in the audit log.'),
            ])
            ->action(function (DownloadGrant $record, array $data): void {
                app(AdjustDownloadGrant::class)->revoke($record, (string) $data['reason']);

                Notification::make()->title('Link revoked')->success()->send();
            });
    }

    private static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (DownloadGrant $r): bool => $r->isRevoked())
            ->requiresConfirmation()
            ->action(function (DownloadGrant $record): void {
                app(AdjustDownloadGrant::class)->restore($record);

                Notification::make()->title('Link restored')->success()->send();
            });
    }

    private static function status(DownloadGrant $grant): string
    {
        return match (true) {
            $grant->isRevoked() => 'Revoked',
            $grant->isExpired() => 'Expired',
            $grant->isExhausted() => 'Used up',
            default => $grant->remaining().' left',
        };
    }
}
