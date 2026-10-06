<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Single = 'single';
    case MiniSet = 'mini_set';
    case Collection = 'collection';
    case Bundle = 'bundle';

    /** A bundle delivers the union of its children's files, deduplicated. */
    public function hasChildren(): bool
    {
        return $this === self::Bundle;
    }

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single artwork',
            self::MiniSet => 'Mini set',
            self::Collection => 'Collection',
            self::Bundle => 'Bundle',
        };
    }
}
