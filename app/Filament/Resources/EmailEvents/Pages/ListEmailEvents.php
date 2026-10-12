<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailEvents\Pages;

use App\Filament\Resources\EmailEvents\EmailEventResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailEvents extends ListRecords
{
    protected static string $resource = EmailEventResource::class;
}
