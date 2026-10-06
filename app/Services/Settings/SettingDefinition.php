<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Declares one setting. The registry of these drives validation, encryption
 * and (later) the admin form, so adding a setting means editing one file.
 */
final readonly class SettingDefinition
{
    public function __construct(
        public string $key,
        public string $group,
        public string $type = 'string',
        public bool $secret = false,
        public mixed $default = null,
        public string $validation = 'nullable|string',
        public ?string $label = null,
        public ?string $help = null,
        /**
         * Config key consulted when no database row exists. Must be a config
         * path, never an env var: env() returns null once the config is cached,
         * which would silently drop every fallback in production.
         */
        public ?string $config = null,
    ) {}

    public function label(): string
    {
        return $this->label ?? str(class_basename($this->key))->headline()->toString();
    }
}
