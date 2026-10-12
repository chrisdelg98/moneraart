<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * One category, on one of the three axes a person chooses by hand.
 *
 * Ratio, orientation and colour are read off the uploaded files, so they are
 * not offered here: a category you can type is a category that can disagree
 * with the artwork. See §4.1 and §10.2.
 */
class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Filament wraps a resource form in a two-column schema, so a
            // section that does not claim the full span renders at half the
            // screen width. The same trap as the product form.
            Section::make()->columnSpanFull()->columns(2)->schema([
                Select::make('attribute_id')
                    ->label('Axis')
                    ->required()
                    ->options(fn (): array => Attribute::query()
                        ->whereIn('key', Attribute::MANUAL)
                        ->orderBy('position')
                        ->pluck('label', 'id')
                        ->all())
                    ->disabledOn('edit')
                    ->helperText('Cannot be changed later: products are already filed under it.'),

                TextInput::make('value')
                    ->label('URL name')
                    ->required()
                    ->maxLength(64)
                    ->alphaDash()
                    ->unique(AttributeValue::class, 'value', ignoreRecord: true)
                    // Frozen after creation: it is what ?style=japandi points
                    // at, and every link already shared uses it.
                    ->disabledOn('edit')
                    ->helperText('Appears in shop links. Lower case, dashes only.'),

                TextInput::make('label_en')
                    ->label('Name (English)')
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        // Only while the slug is still untouched, so renaming a
                        // category later never rewrites its URL.
                        if (blank($get('value')) && filled($state)) {
                            $set('value', Str::slug($state));
                        }
                    }),

                TextInput::make('label_es')
                    ->label('Name (Spanish)')
                    ->maxLength(120)
                    ->helperText('Falls back to the English name when empty.'),
            ]),
        ]);
    }
}
