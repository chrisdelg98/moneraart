<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\SettingsRepository;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Pushes the SMTP settings saved in the admin into the mailer at boot, so the
 * owner's changes take effect without a deploy. See §15.1.
 */
class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $settings = $this->app->make(SettingsRepository::class);

            // has(), not get(): get() would pick up the .env fallback and force
            // SMTP at 127.0.0.1 in local development, breaking the log mailer.
            // Only a value saved in the admin should take over.
            if (! $settings->has('mail.host')) {
                return;
            }

            $host = $settings->get('mail.host');
        } catch (Throwable) {
            // Before the settings table exists — during install or a fresh
            // migrate — fall back to whatever .env provides.
            return;
        }

        if (! is_string($host) || $host === '') {
            return;
        }

        // Symfony's mailer takes a transport scheme, not the old encryption
        // name: STARTTLS on 587 is "smtp", implicit TLS on 465 is "smtps".
        // Passing "tls" through throws on the first real send.
        $scheme = match ($settings->get('mail.encryption', 'tls')) {
            'ssl' => 'smtps',
            default => 'smtp',
        };

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $settings->get('mail.port', 587),
            'mail.mailers.smtp.username' => $settings->get('mail.username'),
            'mail.mailers.smtp.password' => $settings->get('mail.password'),
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.from.address' => $settings->get('mail.from_address') ?: config('mail.from.address'),
            'mail.from.name' => $settings->get('store.name') ?: config('mail.from.name'),
        ]);
    }
}
