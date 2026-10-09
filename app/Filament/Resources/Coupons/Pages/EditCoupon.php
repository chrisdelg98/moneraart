<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Coupons\CouponResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    use PutsFormActionsInHeader;

    protected static string $resource = CouponResource::class;

    /** @return array<Action> */
    protected function getRecordActions(): array
    {
        return [
            // Deleting cascades to the usage rows. The orders keep coupon_code
            // as their own record of what the customer typed.
            DeleteAction::make(),
        ];
    }
}
