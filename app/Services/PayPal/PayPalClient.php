<?php

declare(strict_types=1);

namespace App\Services\PayPal;

use App\Enums\PayPalMode;
use App\Services\PayPal\Exceptions\PayPalAuthException;
use App\Services\PayPal\Exceptions\PayPalRequestException;
use App\Services\Settings\SettingsRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transport and authentication only. Nothing about orders lives here.
 *
 * See §6.3.
 */
final class PayPalClient
{
    /** Never serve a token about to expire — one that dies mid-capture is a lost sale. */
    private const TOKEN_SAFETY_MARGIN = 300;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CacheRepository $cache,
    ) {}

    public function mode(): PayPalMode
    {
        return PayPalMode::from((string) $this->settings->get('paypal.mode', 'sandbox'));
    }

    public function clientId(): ?string
    {
        $value = $this->settings->get($this->mode()->settingKey('client_id'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function clientSecret(): ?string
    {
        $value = $this->settings->get($this->mode()->settingKey('client_secret'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function webhookId(): ?string
    {
        $value = $this->settings->get($this->mode()->settingKey('webhook_id'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    public function baseUrl(): string
    {
        return $this->mode()->baseUrl();
    }

    public function token(): string
    {
        // Keyed by mode and by a hash of the client id, so rotating credentials
        // or flipping mode invalidates the cached token immediately.
        $key = sprintf(
            'paypal:token:%s:%s',
            $this->mode()->value,
            substr(hash('sha256', (string) $this->clientId()), 0, 16),
        );

        $cached = $this->cache->get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $ttl] = $this->fetchToken();

        $this->cache->put($key, $token, max($ttl - self::TOKEN_SAFETY_MARGIN, 60));

        return $token;
    }

    public function forgetToken(): void
    {
        $this->cache->forget(sprintf(
            'paypal:token:%s:%s',
            $this->mode()->value,
            substr(hash('sha256', (string) $this->clientId()), 0, 16),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = [], array $headers = []): array
    {
        $response = $this->send($method, $path, $payload, $headers);

        // A 401 after a successful token fetch means the token was rotated or
        // revoked. Clear it and replay once before giving up.
        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->send($method, $path, $payload, $headers);
        }

        if ($response->failed()) {
            throw PayPalRequestException::fromResponse($method, $path, $response);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    /**
     * PATCH takes a JSON array of operations rather than an object, which the
     * array<string, mixed> contract on request() cannot express.
     *
     * @param  list<array<string, mixed>>  $operations
     * @return array<string, mixed>
     */
    public function patchOperations(string $path, array $operations): array
    {
        $response = Http::withToken($this->token())
            ->timeout((int) config('paypal.timeout', 15))
            ->acceptJson()
            ->patch($this->baseUrl().$path, $operations);

        if ($response->failed()) {
            throw PayPalRequestException::fromResponse('PATCH', $path, $response);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $path, array $payload, array $headers): Response
    {
        $request = Http::withToken($this->token())
            ->withHeaders($headers)
            ->timeout((int) config('paypal.timeout', 15))
            ->retry((int) config('paypal.retries', 3), 200, throw: false)
            ->acceptJson()
            ->asJson();

        $url = $this->baseUrl().$path;

        return match (strtoupper($method)) {
            'GET' => $request->get($url, $payload),
            'POST' => $request->post($url, $payload),
            'PATCH' => $request->patch($url, $payload),
            default => $request->send(strtoupper($method), $url, ['json' => $payload]),
        };
    }

    /** @return array{0: string, 1: int} token and its lifetime in seconds */
    private function fetchToken(): array
    {
        if (! $this->isConfigured()) {
            throw PayPalAuthException::notConfigured();
        }

        $response = Http::asForm()
            ->withBasicAuth((string) $this->clientId(), (string) $this->clientSecret())
            ->timeout((int) config('paypal.timeout', 15))
            ->retry((int) config('paypal.retries', 3), 200, throw: false)
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->failed()) {
            // The secret must never reach a log. Status and mode are enough to
            // diagnose; the body can echo the credentials back.
            Log::channel(config('logging.default'))->warning('paypal.auth.failed', [
                'status' => $response->status(),
                'mode' => $this->mode()->value,
            ]);

            throw PayPalAuthException::rejected($response->status(), $this->mode());
        }

        $token = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);

        if (! is_string($token) || $token === '') {
            throw PayPalAuthException::rejected($response->status(), $this->mode());
        }

        return [$token, $expiresIn];
    }
}
