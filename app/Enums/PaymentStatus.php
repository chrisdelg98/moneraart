<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Declined = 'declined';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Reversed = 'reversed';

    public function isSettled(): bool
    {
        return $this === self::Completed;
    }

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
