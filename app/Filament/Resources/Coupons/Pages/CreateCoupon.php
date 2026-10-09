<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    use PutsFormActionsInHeader;

    protected static string $resource = CouponResource::class;
}
