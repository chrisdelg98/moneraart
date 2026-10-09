<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Providers\MailConfigServiceProvider;
use App\Support\Facades\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * SMTP, with a test send beside the fields that control it.
 *
 * A download email in the spam folder is an undelivered product and a refund
 * request, so this page also checks the DNS records that decide that. See §15.
 *
 * @property-read Schema $form
 */
class EmailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Email';

    protected static UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?string $title = 'Email';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.email-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'host' => Settings::get('mail.host'),
            'port' => Settings::get('mail.port', 587),
            'username' => Settings::get('mail.username'),
            'password' => null,
            'encryption' => Settings::get('mail.encryption', 'tls'),
            'from_address' => Settings::get('mail.from_address'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Sending server')
                ->description('From your email provider — Postmark, Resend, Mailgun, or your host.')
                ->columns(2)
                ->schema([
                    TextInput::make('host')->label('SMTP host')->placeholder('smtp.postmarkapp.com'),
                    TextInput::make('port')->label('Port')->numeric()->default(587),
                    TextInput::make('username')->label('Username')->autocomplete(false),
                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->placeholder(fn (): string => Settings::maskedHint('mail.password') ?? 'Not set')
                        ->helperText('Leave empty to keep the saved one.'),
                    Select::make('encryption')
                        ->label('Encryption')
                        ->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'])
                        ->default('tls')
                        ->native(false),
                    TextInput::make('from_address')
                        ->label('Send from')
                        ->email()
                        ->placeholder('orders@yourdomain.com')
                        ->helperText('Must be an address your provider is allowed to send as.'),
                ]),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save')->icon(Heroicon::OutlinedCheck)->action('save'),

            Action::make('test')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->action('sendTest'),
        ];
    }

    public function save(): void
    {
        $this->persist();

        Notification::make()->title('Saved')
            ->body('Send a test email to confirm it works.')->success()->send();
    }

    public function sendTest(): void
    {
        $this->persist();

        $to = (string) (auth()->user()->email ?? '');

        try {
            Mail::raw(
                "This is a test from your store.\n\nIf you are reading it, order emails will reach your customers.",
                fn ($message) => $message->to($to)->subject('Test — '.Settings::get('store.name')),
            );

            Notification::make()->title("Sent to {$to}")
                ->body('Check your inbox, and your spam folder.')->success()->send();
        } catch (Throwable $e) {
            // The provider's own words are what tells the owner which field is
            // wrong; a generic "could not send" tells them nothing.
            Notification::make()->title('That did not send')
                ->body($e->getMessage())->danger()->persistent()->send();
        }
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function checks(): array
    {
        $from = (string) (Settings::get('mail.from_address') ?? '');

        if (! Settings::has('mail.host')) {
            return [[
                'label' => 'Not configured',
                'ok' => false,
                'detail' => 'Order and download emails are being written to the log, not sent.',
            ]];
        }

        $checks = [[
            'label' => 'Server set',
            'ok' => true,
            'detail' => Settings::get('mail.host').':'.Settings::get('mail.port', 587),
        ]];

        $domain = str($from)->after('@')->toString();

        if ($domain === '') {
            return [...$checks, [
                'label' => 'Send-from address',
                'ok' => false,
                'detail' => 'Not set. Most providers reject mail without one.',
            ]];
        }

        // SPF and DMARC decide whether a download email lands in the inbox or
        // the spam folder, which is the difference between a delivered product
        // and a refund request. See §15.3.
        foreach ([['SPF', $domain, 'v=spf1'], ['DMARC', "_dmarc.{$domain}", 'v=DMARC1']] as [$label, $lookup, $needle]) {
            $records = @dns_get_record($lookup, DNS_TXT) ?: [];
            $found = collect($records)->contains(fn (array $r): bool => str_contains((string) ($r['txt'] ?? ''), $needle));

            $checks[] = [
                'label' => "{$label} record",
                'ok' => $found,
                'detail' => $found
                    ? "Found on {$domain}."
                    : "Not found on {$domain}. Your provider will tell you what to add.",
            ];
        }

        return $checks;
    }

    private function persist(): void
    {
        $state = $this->form->getState();

        Settings::setMany([
            'mail.host' => $state['host'] ?? null,
            'mail.port' => $state['port'] ?? 587,
            'mail.username' => $state['username'] ?? null,
            'mail.encryption' => $state['encryption'] ?? 'tls',
            'mail.from_address' => $state['from_address'] ?? null,
        ]);

        // Empty means keep, never clear.
        if (filled($state['password'] ?? null)) {
            Settings::set('mail.password', $state['password']);
        }

        // Re-apply in-process, so the test send that follows uses what was
        // just typed rather than what was loaded at boot.
        app(MailConfigServiceProvider::class, ['app' => app()])->boot();
    }
}
