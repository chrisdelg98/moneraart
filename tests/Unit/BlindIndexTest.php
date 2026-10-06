<?php

declare(strict_types=1);

use App\Support\BlindIndex;

it('normalises case and whitespace before hashing', function (): void {
    $expected = BlindIndex::email('buyer@example.com');

    expect(BlindIndex::email('BUYER@example.com'))->toBe($expected)
        ->and(BlindIndex::email('  buyer@example.com  '))->toBe($expected)
        ->and(BlindIndex::email('Buyer@Example.COM'))->toBe($expected);
});

it('produces a stable 64-character hex digest', function (): void {
    expect(BlindIndex::email('buyer@example.com'))->toMatch('/^[0-9a-f]{64}$/');
});

it('distinguishes different addresses', function (): void {
    expect(BlindIndex::email('a@example.com'))->not->toBe(BlindIndex::email('b@example.com'));
});

it('is not a plain sha256, so a dictionary attack fails', function (): void {
    expect(BlindIndex::email('buyer@example.com'))->not->toBe(hash('sha256', 'buyer@example.com'));
});

it('compares in constant time', function (): void {
    $hash = BlindIndex::email('buyer@example.com');

    expect(BlindIndex::matches('buyer@example.com', $hash))->toBeTrue()
        ->and(BlindIndex::matches('other@example.com', $hash))->toBeFalse();
});

it('returns null for absent ip and user agent', function (): void {
    expect(BlindIndex::ip(null))->toBeNull()
        ->and(BlindIndex::ip(''))->toBeNull()
        ->and(BlindIndex::userAgent(null))->toBeNull();
});
