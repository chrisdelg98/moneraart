<?php

declare(strict_types=1);

namespace App\Services\Cart;

final readonly class CartOutcome
{
    private function __construct(
        public bool $ok,
        public ?string $reason = null,
    ) {}

    public static function added(): self
    {
        return new self(true);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
