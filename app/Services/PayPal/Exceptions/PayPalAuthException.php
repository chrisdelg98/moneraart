<?php

declare(strict_types=1);

namespace App\Services\PayPal\Exceptions;

use App\Enums\PayPalMode;
use RuntimeException;

class PayPalAuthException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('PayPal is not configured. Add your client ID and secret in Settings → Payments.');
    }

    public static function rejected(int $status, PayPalMode $mode): self
    {
        // The mode-aware wording is the point: pasting Sandbox keys into Live
        // is the most common way this fails, and a generic "unauthorised"
        // leaves the owner with nothing to act on.
        $other = $mode->isLive() ? 'Sandbox' : 'Live';

        return new self(sprintf(
            'PayPal rejected these credentials (HTTP %d). Check they were copied from your %s app, not the %s one.',
            $status,
            ucfirst($mode->value),
            $other,
        ));
    }
}
