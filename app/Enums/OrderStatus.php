<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The order state machine. OrderService::transition() is the only code
 * permitted to write orders.status, and it asserts canTransitionTo() first.
 *
 * `processing` from the original plan is intentionally absent: for digital
 * goods the gap between paid and completed is a queue job measured in
 * milliseconds, and a status nobody ever sees is a status nobody should
 * maintain.
 *
 * See docs/implementation_plan.md §5.1.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Completed = 'completed';
    case ManualReview = 'manual_review';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Failed, self::ManualReview, self::Cancelled],
            self::Paid => [self::Completed, self::ManualReview, self::Refunded],
            self::ManualReview => [self::Completed, self::Cancelled, self::Refunded],
            self::Completed => [self::Refunded],
            self::Failed => [self::Cancelled, self::Pending],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** The customer has paid and is entitled to their files. */
    public function isFulfillable(): bool
    {
        return in_array($this, [self::Paid, self::Completed], true);
    }

    /** Someone is waiting on a human. The dashboard surfaces these first. */
    public function needsAttention(): bool
    {
        return $this === self::ManualReview;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Completed => 'Completed',
            self::ManualReview => 'Manual review',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Paid => 'info',
            self::Completed => 'success',
            self::ManualReview => 'danger',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
            self::Refunded => 'warning',
        };
    }
}
