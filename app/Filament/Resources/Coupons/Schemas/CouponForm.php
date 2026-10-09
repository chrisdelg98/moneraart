<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * The same two-column shape as the product form: what the coupon *is* on the
 * left, what governs it on the right.
 *
 * Every limit is optional and starts empty, because a coupon with no limits is
 * the common case, and a form that demands four numbers before it will create
 * "10% off" is a form nobody uses twice. See §14.2.
 */
class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(12)->columnSpanFull()->schema([
                Group::make()->columnSpan(['default' => 12, 'lg' => 8])->schema([
                    self::codeSection(),
                    self::discountSection(),
                    self::scopeSection(),
                ]),

                Group::make()->columnSpan(['default' => 12, 'lg' => 4])->schema([
                    self::windowSection(),
                    self::limitsSection(),
                ]),
            ]),
        ]);
    }

    private static function codeSection(): Section
    {
        return Section::make('Code')->columns(3)->schema([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(64)
                ->columnSpan(2)
                // Stored uppercase, so the lookup is a plain indexed match and
                // SPRING and spring are never two different coupons.
                //
                // The rule has to be told about that. Checking the raw input
                // lets "taken" through against a stored TAKEN, and the insert
                // then fails on the index as a 500 rather than a form error.
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, ?string $state): Unique => $rule
                    ->where('code', Coupon::normalise((string) $state)))
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', Coupon::normalise((string) $state)))
                ->dehydrateStateUsing(fn (string $state): string => Coupon::normalise($state))
                ->helperText('Case does not matter to the customer.')
                ->suffixAction(
                    Action::make('generate')
                        ->icon('heroicon-m-sparkles')
                        ->label('Generate')
                        ->action(fn (Set $set) => $set('code', Str::upper(Str::random(8)))),
                ),

            TextInput::make('description')
                ->label('Internal note')
                ->maxLength(255)
                ->columnSpanFull()
                ->placeholder('Spring newsletter, sent 12 March')
                ->helperText('Never shown to the customer.'),
        ]);
    }

    private static function discountSection(): Section
    {
        return Section::make('Discount')->columns(3)->schema([
            Select::make('type')
                ->label('Type')
                ->required()
                ->live()
                ->default(CouponType::Percent->value)
                ->options(collect(CouponType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),

            TextInput::make('value')
                ->label('Amount')
                ->numeric()
                ->required()
                ->minValue(1)
                // One column, two meanings: whole percent, or money in cents.
                ->prefix(fn (Get $get): ?string => self::isFixed($get) ? '$' : null)
                ->suffix(fn (Get $get): ?string => self::isFixed($get) ? null : '%')
                ->maxValue(fn (Get $get): ?int => self::isFixed($get) ? null : 100)
                ->formatStateUsing(fn (?int $state, Get $get): ?string => self::isFixed($get)
                    ? self::money($state)
                    : ($state === null ? null : (string) $state))
                ->dehydrateStateUsing(fn (?string $state, Get $get): int => self::isFixed($get)
                    ? (int) round(((float) $state) * 100)
                    : (int) $state),

            TextInput::make('max_discount_cents')
                ->label('Maximum discount')
                ->numeric()
                ->prefix('$')
                ->visible(fn (Get $get): bool => ! self::isFixed($get))
                ->formatStateUsing(fn (?int $state): ?string => self::money($state))
                ->dehydrateStateUsing(fn (?string $state): ?int => self::moneyToCents($state))
                ->helperText('The ceiling a percentage can reach on a large cart.'),

            TextInput::make('min_subtotal_cents')
                ->label('Minimum subtotal')
                ->numeric()
                ->prefix('$')
                ->formatStateUsing(fn (?int $state): ?string => self::money($state))
                ->dehydrateStateUsing(fn (?string $state): ?int => self::moneyToCents($state))
                ->helperText('Quoted back to a customer who falls short.'),
        ]);
    }

    private static function scopeSection(): Section
    {
        return Section::make('What it applies to')
            ->description('The discount is computed on the matching artwork only, never on the rest of the cart.')
            ->schema([
                Select::make('applies_to')
                    ->label('Scope')
                    ->required()
                    ->live()
                    ->default(CouponScope::All->value)
                    ->options(collect(CouponScope::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),

                Select::make('products')
                    ->label('Artwork')
                    ->relationship('products', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->title())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('applies_to') === CouponScope::Products->value),

                Select::make('collections')
                    ->label('Collections')
                    ->relationship('collections', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Collection $record): string => $record->title())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('applies_to') === CouponScope::Collections->value),
            ]);
    }

    private static function windowSection(): Section
    {
        return Section::make('Window')->schema([
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Switching this off stops the code at once, whatever the dates say.'),

            DateTimePicker::make('starts_at')
                ->label('Starts')
                ->seconds(false)
                ->placeholder('Immediately'),

            DateTimePicker::make('expires_at')
                ->label('Expires')
                ->seconds(false)
                ->after('starts_at')
                ->placeholder('Never'),
        ]);
    }

    private static function limitsSection(): Section
    {
        return Section::make('Limits')
            ->description('Leave empty for no limit.')
            ->schema([
                TextInput::make('usage_limit')
                    ->label('Total uses')
                    ->numeric()
                    ->minValue(1),

                TextInput::make('usage_limit_per_customer')
                    ->label('Uses per customer')
                    ->numeric()
                    ->minValue(1),

                TextInput::make('used_count')
                    ->label('Claimed so far')
                    ->disabled()
                    ->dehydrated(false)
                    // A use is held from the moment an order claims it, so this
                    // counts carts still at PayPal as well as completed sales.
                    ->helperText('Includes orders still being paid.'),
            ]);
    }

    private static function isFixed(Get $get): bool
    {
        return $get('type') === CouponType::Fixed->value;
    }

    private static function money(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }

    private static function moneyToCents(?string $state): ?int
    {
        return blank($state) ? null : (int) round(((float) $state) * 100);
    }
}
