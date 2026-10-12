<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['attribute', 'translations'])
                ->withCount('products'))
            // Grouped by axis, because "all the rooms together" is the only
            // order anyone reads this list in.
            ->defaultGroup('attribute.label')
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('label_en')
                    ->label('Name')
                    ->getStateUsing(fn (AttributeValue $r): string => $r->label('en'))
                    ->description(fn (AttributeValue $r): string => $r->label('es'))
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('translations', fn (Builder $q) => $q->where('label', 'like', "%{$search}%")))
                    ->weight('medium'),

                TextColumn::make('value')
                    ->label('URL name')
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('products_count')
                    ->label('Artwork')
                    ->alignEnd()
                    ->sortable()
                    // Zero is the number that matters: a category nothing is
                    // filed under never reaches a shopper, because the shop
                    // only offers facets something uses.
                    ->color(fn (AttributeValue $r): string => $r->products_count === 0 ? 'gray' : 'primary')
                    ->description(fn (AttributeValue $r): ?string => $r->products_count === 0 ? 'Not shown yet' : null),
            ])
            ->filters([
                SelectFilter::make('attribute_id')
                    ->label('Axis')
                    ->options(fn (): array => Attribute::query()
                        ->whereIn('key', Attribute::MANUAL)
                        ->orderBy('position')
                        ->pluck('label', 'id')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    // Deleting one in use detaches it from every product
                    // silently, which is a catalogue edit disguised as a
                    // tidy-up. Empty it first, deliberately.
                    ->before(function (AttributeValue $record, DeleteAction $action): void {
                        $used = $record->products()->count();

                        if ($used === 0) {
                            return;
                        }

                        Notification::make()
                            ->title('Still in use')
                            ->body("{$used} ".str('artwork')->plural($used).' filed under this. Move them first.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->cancel();
                    }),
            ])
            ->emptyStateHeading('No categories on this axis')
            ->emptyStateDescription('Add one and it becomes a filter in the shop as soon as artwork uses it.');
    }
}
