<?php

declare(strict_types=1);

use App\Filament\Pages\PaymentSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayPal\PayPalHealthCheck;
use App\Support\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

/** PayPal accepting or rejecting the credentials, plus webhook endpoints. */
function fakePayPal(bool $authOk = true, array $webhooks = []): void
{
    Http::fake([
        '*/v1/oauth2/token' => $authOk
            ? Http::response(['access_token' => 'tok', 'expires_in' => 32400])
            : Http::response(['error' => 'invalid_client'], 401),
        '*/v1/notifications/webhooks' => Http::response(['webhooks' => $webhooks]),
    ]);
}

it('renders the payments page', function (): void {
    $this->get(PaymentSettings::getUrl())->assertOk();
});

it('saves credentials and encrypts the secret', function (): void {
    fakePayPal();

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'sandbox', 'client_id' => 'AaBbCc', 'client_secret' => 'super-secret'])
        ->call('testConnection');

    expect(Settings::get('paypal.sandbox.client_id'))->toBe('AaBbCc')
        ->and(Settings::get('paypal.sandbox.client_secret'))->toBe('super-secret')
        // Stored ciphertext, not the value.
        ->and(Setting::where('key', 'paypal.sandbox.client_secret')->value('value'))
        ->not->toContain('super-secret');
});

it('keeps the saved secret when the field is left empty', function (): void {
    fakePayPal();
    Settings::set('paypal.sandbox.client_secret', 'original-secret');

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'sandbox', 'client_id' => 'AaBbCc', 'client_secret' => null])
        ->call('testConnection');

    // An empty field means "keep what is saved", never "clear it".
    expect(Settings::get('paypal.sandbox.client_secret'))->toBe('original-secret');
});

it('never renders a stored secret back to the browser', function (): void {
    Settings::set('paypal.sandbox.client_secret', 'super-secret-value');

    livewire(PaymentSettings::class)
        ->assertFormSet(['client_secret' => null])
        ->assertDontSee('super-secret-value');
});

it('keeps sandbox and live credentials apart', function (): void {
    fakePayPal();

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'sandbox', 'client_id' => 'sandbox-id', 'client_secret' => 'sandbox-secret'])
        ->call('testConnection');

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'live', 'client_id' => 'live-id', 'client_secret' => 'live-secret'])
        ->call('testConnection');

    // Switching mode must never destroy the other environment's keys.
    expect(Settings::get('paypal.sandbox.client_id'))->toBe('sandbox-id')
        ->and(Settings::get('paypal.sandbox.client_secret'))->toBe('sandbox-secret')
        ->and(Settings::get('paypal.live.client_id'))->toBe('live-id')
        ->and(Settings::get('paypal.live.client_secret'))->toBe('live-secret');
});

it('reports rejected credentials rather than failing silently', function (): void {
    fakePayPal(authOk: false);

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'live', 'client_id' => 'wrong', 'client_secret' => 'wrong'])
        ->call('testConnection');

    expect(Settings::get('paypal.enabled'))->toBeFalse();
});

it('registers the webhook when payments are enabled', function (): void {
    // The step WooCommerce leaves to the owner. See §6.6.
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),
        '*/v1/notifications/webhooks' => Http::sequence()
            ->push(['webhooks' => []])
            ->push(['id' => 'WH-NEW-ID']),
    ]);
    config(['app.url' => 'https://moneraart.com']);
    URL::forceRootUrl('https://moneraart.com');

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'live', 'client_id' => 'id', 'client_secret' => 'secret'])
        ->call('saveAndEnable');

    expect(Settings::get('paypal.live.webhook_id'))->toBe('WH-NEW-ID')
        ->and(Settings::get('paypal.enabled'))->toBeTrue();
});

it('refuses to register a webhook PayPal could never reach', function (): void {
    fakePayPal();
    URL::forceRootUrl('http://localhost:8000');

    livewire(PaymentSettings::class)
        ->fillForm(['mode' => 'sandbox', 'client_id' => 'id', 'client_secret' => 'secret'])
        ->call('saveAndEnable');

    // Better a clear warning than orders silently stuck at pending.
    expect(Settings::get('paypal.enabled'))->toBeFalse();
});

it('shows what is not configured yet', function (): void {
    $checks = app(PayPalHealthCheck::class)->run();

    expect($checks)->toHaveCount(1)
        ->and($checks[0]['ok'])->toBeFalse()
        ->and($checks[0]['detail'])->toContain('Paste your client ID');
});

it('reports a missing webhook once credentials work', function (): void {
    fakePayPal();
    Settings::setMany([
        'paypal.mode' => 'sandbox',
        'paypal.sandbox.client_id' => 'id',
        'paypal.sandbox.client_secret' => 'secret',
    ]);

    $labels = collect(app(PayPalHealthCheck::class)->run())->pluck('label');

    expect($labels)->toContain('Credentials valid')->toContain('Webhook registered');
});
