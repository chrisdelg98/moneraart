<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The usage report is a sum over the usages, which is one query
            // per row unless it is asked for up front. See §14.2.
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum('usages', 'discount_cents'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->description(fn (Coupon $r): ?string => $r->description),

                TextColumn::make('value')
                    ->label('Discount')
                    ->getStateUsing(fn (Coupon $r): string => $r->describe())
                    ->description(fn (Coupon $r): string => $r->applies_to->label()),

                TextColumn::make('used_count')
                    ->label('Claimed')
                    ->alignEnd()
                    ->sortable()
                    ->getStateUsing(fn (Coupon $r): string => $r->usage_limit === null
                        ? (string) $r->used_count
                        : "{$r->used_count} / {$r->usage_limit}"),

                TextColumn::make('usages_sum_discount_cents')
                    ->label('Given away')
                    ->alignEnd()
                    ->getStateUsing(fn (Coupon $r): string => Money::fromCents(
                        (int) ($r->usages_sum_discount_cents ?? 0),
                        $r->currency,
                    )->format()),

                IconColumn::make('is_active')
                    ->label('Live')
                    // Active is not the same as usable: the dates and the limit
                    // can retire a coupon that is still switched on.
                    ->getStateUsing(fn (Coupon $r): bool => self::isUsable($r))
                    ->boolean(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('j M Y')
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),

                Filter::make('usable')
                    ->label('Usable right now')
                    ->query(fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                        ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->where(fn (Builder $q) => $q->whereNull('usage_limit')
                            ->orWhereColumn('used_count', '<', 'usage_limit'))),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('No coupons yet')
            ->emptyStateDescription('A code is only worth making when you know where it will be sent.');
    }

    private static function isUsable(Coupon $coupon): bool
    {
        return $coupon->is_active
            && ($coupon->starts_at === null || $coupon->starts_at->isPast())
            && ($coupon->expires_at === null || $coupon->expires_at->isFuture())
            && ($coupon->usage_limit === null || $coupon->used_count < $coupon->usage_limit);
    }
}
