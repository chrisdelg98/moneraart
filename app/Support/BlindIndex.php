<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Keyed hashes that make encrypted columns searchable.
 *
 * HMAC, not a plain hash: sha256(email) is trivially reversible against a
 * dictionary of known addresses. The keyed construction is not.
 *
 * BLIND_INDEX_KEY is a separate secret from APP_KEY so that compromising one
 * does not compromise the other.
 *
 * Supports exact lookup only, by design. "Find customers whose email contains
 * gmail" is impossible — which is why customers.email_domain is stored in clear
 * text for the analytics that legitimately need it.
 *
 * See docs/implementation_plan.md §9.3.
 */
final class BlindIndex
{
    public static function email(string $email): string
    {
        return self::hash(mb_strtolower(trim($email)));
    }

    /** IP addresses are never stored in clear text — only hashed, for abuse limits. */
    public static function ip(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : self::hash($ip);
    }

    public static function userAgent(?string $userAgent): ?string
    {
        return $userAgent === null || $userAgent === '' ? null : self::hash($userAgent);
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    /** Constant-time comparison, for verifying a hash we were handed. */
    public static function matches(string $value, string $hash): bool
    {
        return hash_equals($hash, self::hash($value));
    }

    private static function key(): string
    {
        $key = config('app.blind_index_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'BLIND_INDEX_KEY is not set. Run: php artisan store:generate-blind-index-key'
            );
        }

        return str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true) ?: $key
            : $key;
    }
}
