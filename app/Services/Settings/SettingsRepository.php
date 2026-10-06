<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Crypt;

/**
 * Three-tier settings resolution — first hit wins:
 *
 *   1. the `settings` table   (edited in the admin UI)   ← the owner's lane
 *   2. the environment        (.env)                     ← the developer's lane
 *   3. the definition default
 *
 * The whole map is cached under one key, so a request costs one cache read
 * rather than one query per setting.
 *
 * See docs/implementation_plan.md §3.2.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'settings:v1';

    /** @var array<string, string|null>|null Decrypted, in-request memo. */
    private ?array $resolved = null;

    public function __construct(private readonly CacheRepository $cache) {}

    public function get(string $key, mixed $fallback = null): mixed
    {
        $definition = SettingRegistry::find($key);
        $stored = $this->stored()[$key] ?? null;

        if ($stored !== null) {
            return $this->cast($stored, $definition);
        }

        if ($definition?->config !== null) {
            $fromConfig = config($definition->config);

            if ($fromConfig !== null && $fromConfig !== '') {
                return $this->cast((string) $fromConfig, $definition);
            }
        }

        return $fallback ?? $definition?->default;
    }

    public function has(string $key): bool
    {
        return ($this->stored()[$key] ?? null) !== null;
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $isSecret = SettingRegistry::isSecret($key);

        $serialised = match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };

        if ($isSecret && $serialised !== null) {
            $serialised = Crypt::encryptString($serialised);
        }

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $serialised,
                'is_encrypted' => $isSecret,
                'updated_by_user_id' => $userId,
            ],
        );

        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $userId);
        }
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->flush();
    }

    /**
     * Last four characters of a secret, for display. Never returns the value.
     */
    public function maskedHint(string $key): ?string
    {
        $value = $this->get($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_repeat('•', 8).mb_substr($value, -4);
    }

    public function flush(): void
    {
        $this->resolved = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Every stored row, decrypted. One cache read per request.
     *
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var array<string, array{value: string|null, is_encrypted: bool}> $rows */
        $rows = $this->cache->rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->get(['key', 'value', 'is_encrypted'])
            ->mapWithKeys(fn (Setting $s): array => [
                $s->key => ['value' => $s->value, 'is_encrypted' => $s->is_encrypted],
            ])
            ->all());

        $out = [];

        foreach ($rows as $key => $row) {
            $value = $row['value'];

            if ($value !== null && $row['is_encrypted']) {
                // A rotated APP_KEY makes old ciphertext undecryptable. Treat it as
                // unset rather than taking the whole application down — the admin
                // sees an empty field and can re-enter the value.
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable) {
                    $value = null;
                }
            }

            $out[$key] = $value;
        }

        return $this->resolved = $out;
    }

    private function cast(string $value, ?SettingDefinition $definition): mixed
    {
        return match ($definition?->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array' => json_decode($value, true, flags: JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
