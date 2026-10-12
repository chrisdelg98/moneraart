<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailEvents\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\RowActions;
use App\Models\EmailEvent;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class EmailEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M, H:i')
                    ->description(fn (EmailEvent $r): string => $r->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Email')
                    ->getStateUsing(fn (EmailEvent $r): string => Str::headline($r->type))
                    ->description(fn (EmailEvent $r): ?string => $r->subject)
                    ->wrap(),

                TextColumn::make('email_domain')
                    ->label('To')
                    // The address is hashed; the domain is what makes a run of
                    // failures at one provider visible.
                    ->getStateUsing(fn (EmailEvent $r): string => '•••@'.($r->email_domain ?? '—'))
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => $state === EmailEvent::FAILED ? 'danger' : 'success'),

                TextColumn::make('error')
                    ->label('What went wrong')
                    ->wrap()
                    ->color('danger')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    EmailEvent::SENT => 'Sent',
                    EmailEvent::FAILED => 'Failed',
                ]),

                SelectFilter::make('type')
                    ->label('Email')
                    ->options(fn (): array => EmailEvent::query()
                        ->distinct()
                        ->pluck('type', 'type')
                        ->map(fn (string $t): string => Str::headline($t))
                        ->all()),
            ])
            ->recordActions(RowActions::style([
                Action::make('order')
                    ->label('Order')
                    ->visible(fn (EmailEvent $r): bool => $r->order !== null)
                    ->url(fn (EmailEvent $r): string => OrderResource::getUrl('view', ['record' => $r->order])),
            ]))
            ->emptyStateHeading('Nothing sent yet')
            ->emptyStateDescription('Every email the store sends is recorded here, delivered or not.');
    }
}
