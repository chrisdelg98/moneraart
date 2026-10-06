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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Three tabs, and the first one alone is enough to publish.
 *
 * The administrator writes three things (title, subtitle, description), picks
 * three (style, room, theme) and drags two (artwork, files). Everything else —
 * slug, SEO title, meta description, alt text, ratio, orientation, colour — is
 * generated or computed. See §13.3.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                self::productTab(),
                self::filesTab(),
                self::seoTab(),
            ]),
        ]);
    }

    private static function productTab(): Tab
    {
        return Tab::make('Product')->schema([
            Section::make()->columns(2)->schema([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('What this piece is called. The slug and SEO title come from it.'),

                TextInput::make('subtitle')
                    ->label('Subtitle')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('One editorial line, shown on listing cards. Optional.'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(6)
                    ->columnSpanFull()
                    ->helperText(
                        'Written for a person, not for search engines — the meta description '
                        .'is generated from this. Required before publishing.'
                    ),
            ]),

            Section::make('Pricing')->columns(3)->schema([
                TextInput::make('price_cents')
                    ->label('Price')
                    ->numeric()
                    ->required()
                    ->prefix('$')
                    ->minValue(config('store.pricing.minimum_price_cents') / 100)
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100))
                    // Below ~$1.99 the fixed per-transaction fee dominates
                    // whatever the sale price is. See §6.12.
                    ->helperText('Minimum $'.number_format(config('store.pricing.minimum_price_cents') / 100, 2)
                        .' — below that, payment fees eat most of the sale.'),

                TextInput::make('sale_price_cents')
                    ->label('Sale price')
                    ->numeric()
                    ->prefix('$')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (?string $state): ?int => $state === null || $state === '' ? null : (int) round(((float) $state) * 100)),

                Select::make('type')
                    ->label('Type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                    ->default(ProductType::Single->value)
                    ->required(),

                DateTimePicker::make('sale_starts_at')->label('Sale starts'),
                DateTimePicker::make('sale_ends_at')->label('Sale ends')
                    ->helperText('Outside this window the full price applies.'),
            ]),

            Section::make('Classification')
                ->description('Three choices. Ratio, orientation and colour are read from your files.')
                ->columns(3)
                ->schema(self::manualAttributeSelects()),

            Section::make('Publishing')->columns(3)->schema([
                Select::make('status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->default(ProductStatus::Draft->value)
                    ->required(),

                DateTimePicker::make('published_at')->label('Publish at'),

                Toggle::make('is_ai_generated')
                    ->label('AI-generated artwork')
                    ->default(true)
                    ->helperText('Shows the disclosure badge on the product page.'),
            ]),
        ]);
    }

    /** @return list<Select> */
    private static function manualAttributeSelects(): array
    {
        return collect(Attribute::MANUAL)
            ->map(fn (string $key): Select => Select::make("attr_{$key}")
                ->label(str($key)->headline()->toString())
                ->multiple($key === 'room')
                ->searchable()
                ->preload()
                ->options(fn (): array => Attribute::where('key', $key)
                    ->first()?->values()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn ($v): array => [$v->id => $v->label('en')])
                    ->all() ?? []))
            ->all();
    }

    private static function filesTab(): Tab
    {
        return Tab::make('Files & Formats')->schema([
            Section::make('Artwork')
                ->description('Public previews. Capped in resolution and watermarked at large sizes — never the file the customer buys.')
                ->schema([
                    // Upload handling is wired in Phase 1b alongside the
                    // queued variant generation.
                ]),

            Section::make('Product files')
                ->description('What the customer downloads. Stored privately and only ever served through a download grant.')
                ->schema([]),
        ]);
    }

    private static function seoTab(): Tab
    {
        return Tab::make('SEO')->schema([
            Section::make()
                ->description('Generated from the product. Editing any field here stops automation touching it again.')
                ->schema([
                    TextInput::make('seo_title')->label('SEO title')->maxLength(60)
                        ->helperText('Leave empty to generate from the title and attributes.'),
                    Textarea::make('seo_description')->label('Meta description')->rows(3)->maxLength(160)
                        ->helperText('Leave empty to generate from the description.'),
                ]),
        ]);
    }
}
