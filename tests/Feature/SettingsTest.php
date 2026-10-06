<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => Settings::flush());

it('falls back to the definition default when nothing is stored', function (): void {
    expect(Settings::get('download.max_downloads'))->toBe(5)
        ->and(Settings::get('download.expiry_hours'))->toBe(72)
        ->and(Settings::get('paypal.mode'))->toBe('sandbox')
        ->and(Settings::get('setup.completed'))->toBeFalse();
});

it('prefers a stored value over the default', function (): void {
    Settings::set('download.max_downloads', 10);

    expect(Settings::get('download.max_downloads'))->toBe(10);
});

it('prefers a stored value over the environment', function (): void {
    config(['app.env' => 'testing']);
    putenv('PAYPAL_MODE=live');

    Settings::set('paypal.mode', 'sandbox');

    expect(Settings::get('paypal.mode'))->toBe('sandbox');

    putenv('PAYPAL_MODE');
});

it('casts stored values to their declared type', function (): void {
    Settings::setMany([
        'download.max_downloads' => '7',
        'setup.completed' => true,
        'seo.allow_indexing' => false,
    ]);

    expect(Settings::get('download.max_downloads'))->toBe(7)
        ->and(Settings::get('setup.completed'))->toBeTrue()
        ->and(Settings::get('seo.allow_indexing'))->toBeFalse();
});

it('encrypts values declared secret', function (): void {
    Settings::set('paypal.live.client_secret', 'super-secret-value');

    $raw = Setting::where('key', 'paypal.live.client_secret')->value('value');

    expect($raw)->not->toBe('super-secret-value')
        ->and($raw)->not->toContain('super-secret')
        ->and(Setting::where('key', 'paypal.live.client_secret')->value('is_encrypted'))->toBeTrue()
        ->and(Settings::get('paypal.live.client_secret'))->toBe('super-secret-value');
});

it('stores non-secret values in clear text', function (): void {
    Settings::set('paypal.live.client_id', 'AaBbCc123');

    expect(Setting::where('key', 'paypal.live.client_id')->value('value'))->toBe('AaBbCc123');
});

it('keeps sandbox and live credentials separate', function (): void {
    Settings::setMany([
        'paypal.sandbox.client_secret' => 'sandbox-secret',
        'paypal.live.client_secret' => 'live-secret',
    ]);

    expect(Settings::get('paypal.sandbox.client_secret'))->toBe('sandbox-secret')
        ->and(Settings::get('paypal.live.client_secret'))->toBe('live-secret');

    // Changing the mode must not touch either credential.
    Settings::set('paypal.mode', 'live');

    expect(Settings::get('paypal.sandbox.client_secret'))->toBe('sandbox-secret')
        ->and(Settings::get('paypal.live.client_secret'))->toBe('live-secret');
});

it('masks a secret instead of echoing it back', function (): void {
    Settings::set('paypal.live.client_secret', 'abcdefgh1234');

    expect(Settings::maskedHint('paypal.live.client_secret'))->toBe('••••••••1234')
        ->and(Settings::maskedHint('paypal.sandbox.client_secret'))->toBeNull();
});

it('reads from cache rather than querying per lookup', function (): void {
    Settings::set('store.name', 'Monera Art');
    Settings::flush();

    Settings::get('store.name');     // warms the cache

    DB::enableQueryLog();
    Settings::get('store.name');
    Settings::get('paypal.mode');
    Settings::get('download.max_downloads');

    expect(DB::getQueryLog())->toBeEmpty();
});

it('invalidates the cache on write', function (): void {
    Settings::set('store.name', 'First');
    expect(Settings::get('store.name'))->toBe('First');

    Settings::set('store.name', 'Second');
    expect(Settings::get('store.name'))->toBe('Second');
});

it('treats an undecryptable secret as unset rather than crashing', function (): void {
    Setting::create([
        'key' => 'paypal.live.client_secret',
        'value' => 'not-valid-ciphertext',
        'is_encrypted' => true,
    ]);

    Settings::flush();

    expect(Settings::get('paypal.live.client_secret'))->toBeNull();
});

it('forgets a setting and returns to the default', function (): void {
    Settings::set('download.max_downloads', 99);
    expect(Settings::get('download.max_downloads'))->toBe(99);

    Settings::forget('download.max_downloads');
    expect(Settings::get('download.max_downloads'))->toBe(5);
});

it('defaults AI assistance to off so every field stays manual', function (): void {
    expect(Settings::get('ai.provider'))->toBe('none')
        ->and(Settings::get('ai.api_key'))->toBeNull()
        ->and(Settings::get('ai.feature.descriptions'))->toBeFalse();
});

it('encrypts the AI api key', function (): void {
    Settings::set('ai.api_key', 'sk-test-abcd1234');

    expect(Setting::where('key', 'ai.api_key')->value('value'))->not->toBe('sk-test-abcd1234')
        ->and(Settings::get('ai.api_key'))->toBe('sk-test-abcd1234')
        ->and(Settings::maskedHint('ai.api_key'))->toBe('••••••••1234');
});

it('lets each AI feature be switched off independently', function (): void {
    Settings::setMany([
        'ai.provider' => 'openai',
        'ai.feature.translations' => true,
        'ai.feature.seo_copy' => false,
    ]);

    expect(Settings::get('ai.feature.translations'))->toBeTrue()
        ->and(Settings::get('ai.feature.seo_copy'))->toBeFalse();
});
