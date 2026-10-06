<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Product $r): string => $r->title('en') ?: '(untitled)')
                    ->description(fn (Product $r): ?string => $r->translate('en')?->subtitle)
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'translations', fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ))
                    ->wrap(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label()),

                TextColumn::make('price_cents')
                    ->label('Price')
                    ->getStateUsing(fn (Product $r): string => $r->effectivePrice()->format())
                    ->description(fn (Product $r): ?string => $r->isOnSale() ? 'was '.$r->price()->format() : null)
                    ->sortable(),

                // Translation status at a glance — the dashboard counts the
                // products still waiting on a locale. See §4.7.2.
                TextColumn::make('locales')
                    ->label('Languages')
                    ->getStateUsing(function (Product $r): string {
                        /** @var list<string> $locales */
                        $locales = config('store.locales', ['en']);
                        $parts = [];

                        foreach ($locales as $locale) {
                            $published = $r->translate($locale)?->status === 'published';
                            $parts[] = strtoupper($locale).' '.($published ? '✓' : '—');
                        }

                        return implode(' · ', $parts);
                    }),

                TextColumn::make('file_count')->label('Files')->alignCenter()->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => match ($state) {
                        ProductStatus::Published => 'success',
                        ProductStatus::Scheduled => 'info',
                        ProductStatus::Archived => 'danger',
                        ProductStatus::Draft => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('sales_count')->label('Sold')->alignCenter()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')->label('Updated')->since()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No artwork yet')
            ->emptyStateDescription('Create your first piece to see it here.');
    }
}
