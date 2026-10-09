<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Storage\StorageManager;
use Illuminate\Support\ServiceProvider;

/**
 * Applies the storage credentials saved in the admin at boot, so switching to
 * R2 is a form submission rather than a deploy. See §8.6.
 */
class StorageConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StorageManager::class)->configure();
    }
}
