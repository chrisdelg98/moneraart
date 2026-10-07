<?php

declare(strict_types=1);

namespace App\Services\PayPal\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class PayPalRequestException extends RuntimeException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $body = [],
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(string $method, string $path, Response $response): self
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        $detail = $body['message'] ?? $body['error_description'] ?? $response->reason();

        return new self(
            sprintf('PayPal %s %s failed (HTTP %d): %s', strtoupper($method), $path, $response->status(), $detail),
            $response->status(),
            $body,
        );
    }
}
