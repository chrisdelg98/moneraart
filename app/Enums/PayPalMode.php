<?php

declare(strict_types=1);

namespace App\Enums;

enum PayPalMode: string
{
    case Sandbox = 'sandbox';
    case Live = 'live';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://api-m.sandbox.paypal.com',
            self::Live => 'https://api-m.paypal.com',
        };
    }

    /** Credentials are stored under separate keys per mode. See §3.4. */
    public function settingKey(string $suffix): string
    {
        return "paypal.{$this->value}.{$suffix}";
    }

    public function isLive(): bool
    {
        return $this === self::Live;
    }
}
