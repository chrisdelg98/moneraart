<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\MailConfigServiceProvider;
use App\Providers\StorageConfigServiceProvider;

return [
    AppServiceProvider::class,
    MailConfigServiceProvider::class,
    StorageConfigServiceProvider::class,
    AdminPanelProvider::class,
];
