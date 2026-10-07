<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Two columns, the way WordPress lays out a post: the wide column is what you
 * write, the narrow one is what you set.
 *
 * Everything needed to publish fits on one screen. Nothing is behind a tab,
 * because a tab hides work rather than reducing it — and the sections that are
 * rarely touched (sale window, SEO overrides) start collapsed instead.
 *
 * See §13.3.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Filament's resource pages wrap the form in a two-column schema,
            // so a grid that does not claim the full span silently renders at
            // half the screen width.
            Grid::make(4)->columnSpanFull()->schema([
                Group::make()->columnSpan(['default' => 4, 'lg' => 3])->schema([
                    self::contentSection(),
                    self::pricingSection(),
                ]),

                Group::make()->columnSpan(['default' => 4, 'lg' => 1])->schema([
                    self::publishSection(),
                    self::classificationSection(),
                    self::seoSection(),
                ]),
            ]),
        ]);
    }

    // ── Main column ──────────────────────────────────────────────────────

    private static function contentSection(): Section
    {
        return Section::make('Content')
            ->description('Written for a person. The SEO text is generated from it.')
            ->columns(2)
            ->schema([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Mid-Century Modern Coffee Bar Wall Art')
                    ->helperText('The URL is generated from this.'),

                TextInput::make('subtitle')
                    ->label('Subtitle')
                    ->maxLength(255)
                    ->placeholder('Warm retro tones for a kitchen nook'),

                Textarea::make('description')
                    ->columnSpanFull()
                    ->label('Description')
                    ->rows(6)
                    ->placeholder('Where the idea came from, how it prints, what it pairs with.')
                    ->helperText('Required to publish.'),
            ]);
    }

    private static function seoSection(): Section
    {
        return Section::make('Search engines')
            ->description('Generated automatically. Fill a field only to override it.')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextInput::make('seo_title')
                    ->label('SEO title')
                    ->maxLength(60)
                    ->placeholder('Generated from the title and attributes'),

                Textarea::make('seo_description')
                    ->label('Meta description')
                    ->rows(3)
                    ->maxLength(160)
                    ->placeholder('Generated from the description'),
            ]);
    }

    // ── Sidebar ──────────────────────────────────────────────────────────

    private static function publishSection(): Section
    {
        return Section::make('Publish')->schema([
            Select::make('status')
                ->label('Status')
                ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                ->default(ProductStatus::Draft->value)
                ->native(false)
                ->required(),

            DateTimePicker::make('published_at')
                ->label('Publish at')
                ->native(false)
                ->placeholder('Immediately'),

            Toggle::make('is_ai_generated')
                ->label('Made with AI')
                ->default(true)
                ->helperText('Shows the disclosure badge.'),
        ]);
    }

    private static function pricingSection(): Section
    {
        $floor = (int) config('store.pricing.minimum_price_cents') / 100;

        // Three across in the wide column: a price field stretched to full
        // width reads as if it expects a long value.
        return Section::make('Pricing')->columns(3)->schema([
            TextInput::make('price_cents')
                ->label('Price')
                ->numeric()
                ->required()
                ->prefix('$')
                ->minValue($floor)
                ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100))
                // Below roughly $1.99 the fixed per-transaction fee takes most
                // of the sale whatever the price is. See §6.12.
                ->helperText('Minimum $'.number_format($floor, 2).'.'),

            Select::make('type')
                ->label('Type')
                ->options(collect(ProductType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                ->default(ProductType::Single->value)
                ->native(false)
                ->required(),

            // Three fields collapse into one checkbox until a sale exists.
            Toggle::make('has_sale')
                ->label('Put on sale')
                ->inline(false)
                ->live()
                ->dehydrated(false)
                ->default(fn (Get $get): bool => filled($get('sale_price_cents'))),

            TextInput::make('sale_price_cents')
                ->label('Sale price')
                ->numeric()
                ->prefix('$')
                ->visible(fn (Get $get): bool => (bool) $get('has_sale'))
                ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                ->dehydrateStateUsing(fn (?string $state): ?int => blank($state) ? null : (int) round(((float) $state) * 100)),

            DateTimePicker::make('sale_starts_at')
                ->label('On sale from')
                ->native(false)
                ->placeholder('Now')
                ->visible(fn (Get $get): bool => (bool) $get('has_sale')),

            DateTimePicker::make('sale_ends_at')
                ->label('Until')
                ->native(false)
                ->placeholder('No end date')
                ->visible(fn (Get $get): bool => (bool) $get('has_sale')),
        ]);
    }

    private static function classificationSection(): Section
    {
        return Section::make('Classification')
            ->description('Ratio, orientation and colour come from your files.')
            ->schema(self::manualAttributeSelects());
    }

    /** @return list<Select> */
    private static function manualAttributeSelects(): array
    {
        $placeholders = [
            'style' => 'Mid-Century Modern',
            'room' => 'Kitchen, Coffee Bar',
            'theme' => 'Coffee',
        ];

        return collect(Attribute::MANUAL)
            ->map(fn (string $key): Select => Select::make("attr_{$key}")
                ->label(str($key)->headline()->toString())
                ->placeholder($placeholders[$key] ?? null)
                ->multiple($key === 'room')
                ->searchable()
                ->preload()
                ->native(false)
                ->options(fn (): array => Attribute::where('key', $key)
                    ->first()?->values()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn ($v): array => [$v->id => $v->label('en')])
                    ->all() ?? []))
            ->all();
    }
}
