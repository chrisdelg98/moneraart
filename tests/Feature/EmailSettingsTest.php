<?php

declare(strict_types=1);

use App\Filament\Pages\EmailSettings;
use App\Models\Setting;
use App\Models\User;
use App\Providers\MailConfigServiceProvider;
use App\Support\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create(['email' => 'owner@example.com'])));

it('renders the email page', function (): void {
    $this->get(EmailSettings::getUrl())->assertOk();
});

it('saves settings and encrypts the password', function (): void {
    livewire(EmailSettings::class)
        ->fillForm([
            'host' => 'smtp.example.com', 'port' => 587,
            'username' => 'user', 'password' => 'smtp-secret',
            'encryption' => 'tls', 'from_address' => 'orders@example.com',
        ])
        ->call('save');

    expect(Settings::get('mail.host'))->toBe('smtp.example.com')
        ->and(Settings::get('mail.password'))->toBe('smtp-secret')
        ->and(Setting::where('key', 'mail.password')->value('value'))->not->toContain('smtp-secret');
});

it('keeps the saved password when the field is empty', function (): void {
    Settings::set('mail.password', 'original');

    livewire(EmailSettings::class)
        ->fillForm(['host' => 'smtp.example.com', 'password' => null])
        ->call('save');

    // Empty means keep, never clear — the mistake that silently stops delivery.
    expect(Settings::get('mail.password'))->toBe('original');
});

it('never renders the stored password back', function (): void {
    Settings::set('mail.password', 'top-secret-value');

    livewire(EmailSettings::class)
        ->assertFormSet(['password' => null])
        ->assertDontSee('top-secret-value');
});

it('maps the encryption choice to a transport scheme symfony accepts', function (): void {
    // "tls" is the old encryption name and throws on the first real send:
    // STARTTLS on 587 is "smtp", implicit TLS on 465 is "smtps".
    Settings::setMany(['mail.host' => 'smtp.example.com', 'mail.encryption' => 'tls']);
    app(MailConfigServiceProvider::class, ['app' => app()])->boot();
    expect(config('mail.mailers.smtp.scheme'))->toBe('smtp');

    Settings::set('mail.encryption', 'ssl');
    app(MailConfigServiceProvider::class, ['app' => app()])->boot();
    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps');
});

it('applies the saved settings to the mailer', function (): void {
    Settings::setMany([
        'mail.host' => 'smtp.example.com',
        'mail.port' => 2525,
        'mail.from_address' => 'orders@example.com',
    ]);

    // The owner's change has to take effect without a deploy. See §15.1.
    app(MailConfigServiceProvider::class, ['app' => app()])->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525);
});
