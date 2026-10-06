<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Money as integer minor units. Floating-point currency never enters this
 * codebase — see docs/implementation_plan.md §5.3.
 *
 * toDecimalString() exists because PayPal requires a string with exactly two
 * decimal places, and producing that from a float is the classic source of
 * one-cent mismatches that park an order in manual review.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $cents,
        public string $currency,
    ) {}

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, strtoupper($currency));
    }

    /**
     * Parse a decimal string such as "12.50". Accepts a string to avoid a float
     * ever representing the value, even in transit.
     */
    public static function fromDecimal(string $amount, string $currency = 'USD'): self
    {
        $amount = trim($amount);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException("Not a valid monetary amount: {$amount}");
        }

        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-')), 2, '0');

        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return new self($negative ? -$cents : $cents, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    /**
     * A percentage of this amount, rounded half-up. Integer arithmetic
     * throughout: intdiv on a pre-rounded numerator, never a float multiply.
     */
    public function percentage(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException("Percentage out of range: {$percent}");
        }

        return new self(intdiv($this->cents * $percent + 50, 100), $this->currency);
    }

    /** Never below zero — used for discounts that would overshoot a total. */
    public function clampToZero(): self
    {
        return $this->cents < 0 ? new self(0, $this->currency) : $this;
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->cents <= $other->cents ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    /** Exactly two decimal places, no thousands separator. What PayPal wants. */
    public function toDecimalString(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public function format(string $locale = 'en_US'): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($this->cents / 100, $this->currency)
            ?: $this->currency.' '.$this->toDecimalString();
    }

    /** @return array{cents: int, currency: string} */
    public function jsonSerialize(): array
    {
        return ['cents' => $this->cents, 'currency' => $this->currency];
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} and {$other->currency}"
            );
        }
    }
}
