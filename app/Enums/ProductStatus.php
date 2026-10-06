<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    /** Archived products return 410 Gone, not 404 — see §11.3. */
    public function isGone(): bool
    {
        return $this === self::Archived;
    }

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
