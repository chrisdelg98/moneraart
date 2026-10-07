<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Payment;

final readonly class PaymentOutcome
{
    private function __construct(
        public string $result,
        public Order $order,
        public ?Payment $payment = null,
        public ?string $reason = null,
    ) {}

    public static function paid(Order $order, Payment $payment): self
    {
        return new self('paid', $order, $payment);
    }

    /** The other path got there first. Not an error. */
    public static function alreadyHandled(Order $order): self
    {
        return new self('already_handled', $order);
    }

    public static function needsReview(Order $order, string $reason): self
    {
        return new self('manual_review', $order, reason: $reason);
    }

    public static function failed(Order $order, string $reason): self
    {
        return new self('failed', $order, reason: $reason);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->result, ['paid', 'already_handled'], true);
    }
}
