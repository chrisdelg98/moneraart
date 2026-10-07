<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\MailConfigServiceProvider;

return [
    AppServiceProvider::class,
    MailConfigServiceProvider::class,
    AdminPanelProvider::class,
];
