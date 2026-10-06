<?php

declare(strict_types=1);

use App\Support\Money;

it('parses decimal strings without floating point drift', function (): void {
    expect(Money::fromDecimal('12.50')->cents)->toBe(1250)
        ->and(Money::fromDecimal('0.99')->cents)->toBe(99)
        ->and(Money::fromDecimal('1.99')->cents)->toBe(199)
        ->and(Money::fromDecimal('17.97')->cents)->toBe(1797)
        ->and(Money::fromDecimal('100')->cents)->toBe(10000)
        ->and(Money::fromDecimal('0.1')->cents)->toBe(10);
});

it('rejects malformed amounts', function (string $bad): void {
    Money::fromDecimal($bad);
})->with(['12.345', 'abc', '', '1,50', '1.2.3'])->throws(InvalidArgumentException::class);

it('always renders exactly two decimal places for PayPal', function (): void {
    expect(Money::fromCents(1250)->toDecimalString())->toBe('12.50')
        ->and(Money::fromCents(1200)->toDecimalString())->toBe('12.00')
        ->and(Money::fromCents(5)->toDecimalString())->toBe('0.05')
        ->and(Money::fromCents(0)->toDecimalString())->toBe('0.00')
        ->and(Money::fromCents(100000)->toDecimalString())->toBe('1000.00');
});

it('survives a decimal round trip', function (string $amount): void {
    expect(Money::fromDecimal($amount)->toDecimalString())->toBe($amount);
})->with(['0.00', '0.01', '1.99', '12.50', '17.97', '1000.00']);

it('adds and subtracts', function (): void {
    $a = Money::fromDecimal('5.99');
    $b = Money::fromDecimal('11.98');

    expect($a->plus($b)->toDecimalString())->toBe('17.97')
        ->and($b->minus($a)->toDecimalString())->toBe('5.99');
});

it('refuses to mix currencies', function (): void {
    Money::fromCents(100, 'USD')->plus(Money::fromCents(100, 'EUR'));
})->throws(InvalidArgumentException::class);

it('computes percentages with half-up rounding', function (): void {
    // 20% of 17.97 = 3.594 -> 3.59
    expect(Money::fromDecimal('17.97')->percentage(20)->toDecimalString())->toBe('3.59')
        // 50% of 0.99 = 0.495 -> 0.50
        ->and(Money::fromDecimal('0.99')->percentage(50)->toDecimalString())->toBe('0.50')
        ->and(Money::fromDecimal('10.00')->percentage(100)->toDecimalString())->toBe('10.00')
        ->and(Money::fromDecimal('10.00')->percentage(0)->toDecimalString())->toBe('0.00');
});

it('keeps a PayPal breakdown summing to the cent', function (): void {
    // item_total - discount must equal amount.value exactly, or PayPal rejects it.
    $items = Money::fromDecimal('5.99')
        ->plus(Money::fromDecimal('5.99'))
        ->plus(Money::fromDecimal('5.99'));

    $discount = $items->percentage(20);
    $total = $items->minus($discount);

    expect($items->toDecimalString())->toBe('17.97')
        ->and($discount->toDecimalString())->toBe('3.59')
        ->and($total->toDecimalString())->toBe('14.38')
        ->and($items->minus($discount)->equals($total))->toBeTrue();
});

it('clamps a discount that would overshoot the total', function (): void {
    expect(Money::fromDecimal('5.00')->minus(Money::fromDecimal('8.00'))->clampToZero()->isZero())
        ->toBeTrue();
});
