<?php

declare(strict_types=1);

namespace App\Services\PayPal;

final readonly class WebhookProvisionResult
{
    private function __construct(
        public bool $ok,
        public string $state,
        public ?string $webhookId = null,
        public ?string $message = null,
    ) {}

    public static function created(string $id): self
    {
        return new self(true, 'created', $id, 'Webhook registered with PayPal.');
    }

    public static function alreadyCorrect(string $id): self
    {
        return new self(true, 'ok', $id, 'Webhook already registered and up to date.');
    }

    /** The event list had drifted — some events delivered, others silently dropped. */
    public static function repaired(string $id): self
    {
        return new self(true, 'repaired', $id, 'Webhook event list was out of date and has been corrected.');
    }

    public static function skipped(string $why): self
    {
        return new self(false, 'skipped', null, $why);
    }

    public static function failed(string $why): self
    {
        return new self(false, 'failed', null, $why);
    }
}
