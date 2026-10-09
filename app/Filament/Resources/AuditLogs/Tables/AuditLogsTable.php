<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The record of who did what.
 *
 * Read only, in the strongest sense: no create, no edit, no delete, no bulk
 * action. A log that can be edited from the screen it is read on is not
 * evidence of anything. See §19.
 */
class AuditLogsTable
{
    /** Every action the application writes, with a sentence a person can read. */
    public const ACTIONS = [
        'customer.email_revealed' => 'Revealed a customer address',
        'order.approved' => 'Approved an order held for review',
        'order.refunded' => 'Refunded an order',
        'order.reissue' => 'Reissued download links',
        'order.resend' => 'Resent the download email',
        'settings.updated' => 'Changed a setting',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i')
                    ->description(fn (AuditLog $r): string => $r->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('action')
                    ->label('What happened')
                    ->getStateUsing(fn (AuditLog $r): string => self::ACTIONS[$r->action] ?? Str::headline($r->action))
                    ->description(fn (AuditLog $r): ?string => self::describeMetadata($r))
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label('Who')
                    // A null user means a job or a console command did it,
                    // which is a different fact from "we do not know".
                    ->placeholder('Automatic')
                    ->sortable(),

                TextColumn::make('auditable_type')
                    ->label('On')
                    ->getStateUsing(fn (AuditLog $r): ?string => $r->auditable_type === null
                        ? null
                        : class_basename($r->auditable_type).' #'.$r->auditable_id)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options(self::ACTIONS),

                Filter::make('since')
                    ->schema([DatePicker::make('since')->label('Since')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['since'] ?? null,
                        fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['since'] ?? null)
                        ? 'Since '.$data['since']
                        : null),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-m-eye')
                    ->visible(fn (AuditLog $r): bool => filled($r->metadata))
                    ->modalHeading(fn (AuditLog $r): string => self::ACTIONS[$r->action] ?? $r->action)
                    ->modalContent(fn (AuditLog $r) => view('filament.audit-log-details', ['entry' => $r]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateHeading('Nothing recorded yet')
            ->emptyStateDescription('Revealing an address, approving an order or issuing a refund all land here.');
    }

    /** A one-line summary of the metadata, so the common case needs no click. */
    private static function describeMetadata(AuditLog $entry): ?string
    {
        $meta = $entry->metadata;

        if ($meta === null || $meta === []) {
            return null;
        }

        foreach (['reason', 'amount', 'grants'] as $key) {
            if (isset($meta[$key]) && is_scalar($meta[$key])) {
                return Str::headline($key).': '.$meta[$key];
            }
        }

        return null;
    }
}
