<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $fallback = null)
 * @method static bool has(string $key)
 * @method static void set(string $key, mixed $value, ?int $userId = null)
 * @method static void setMany(array<string, mixed> $values, ?int $userId = null)
 * @method static void forget(string $key)
 * @method static string|null maskedHint(string $key)
 * @method static void flush()
 *
 * @see SettingsRepository
 */
final class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsRepository::class;
    }
}
