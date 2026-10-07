<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\PayPalMode;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\PayPalHealthCheck;
use App\Services\PayPal\PayPalWebhookService;
use App\Support\Facades\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Three fields and one button.
 *
 * Everything else — the webhook, its event list, the merchant name — is derived
 * and verified. Where WooCommerce asks the owner to register a webhook by hand
 * in PayPal's dashboard, this does it for them, which removes the most common
 * cause of orders stuck at pending. See §6.1 and §6.2.
 *
 * @property-read Schema $form
 */
class PaymentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $title = 'Payments';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.payment-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $mode = PayPalMode::from((string) Settings::get('paypal.mode', 'sandbox'));

        $this->form->fill([
            'mode' => $mode->value,
            'client_id' => Settings::get($mode->settingKey('client_id')),
            // Never render a stored secret back to the browser.
            'client_secret' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('PayPal')
                ->description('Paste the two keys from your PayPal app. Everything else is set up for you.')
                ->schema([
                    Radio::make('mode')
                        ->label('Mode')
                        ->options([
                            PayPalMode::Sandbox->value => 'Sandbox — for testing',
                            PayPalMode::Live->value => 'Live — real payments',
                        ])
                        ->default(PayPalMode::Sandbox->value)
                        ->live()
                        ->afterStateUpdated(fn () => $this->loadCredentialsForMode())
                        ->required()
                        // Sandbox and live credentials are stored separately, so
                        // switching mode never destroys the other set. See §3.4.
                        ->helperText('Each mode keeps its own keys. Switching does not erase the other.'),

                    TextInput::make('client_id')
                        ->label('Client ID')
                        ->autocomplete(false)
                        ->maxLength(191),

                    TextInput::make('client_secret')
                        ->label('Secret')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->maxLength(191)
                        ->placeholder(fn (): string => $this->maskedSecret() ?? 'Not set')
                        ->helperText('Leave empty to keep the secret you already saved.'),
                ]),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test connection')
                ->icon(Heroicon::OutlinedBolt)
                ->color('gray')
                ->action('testConnection'),

            Action::make('save')
                ->label('Save and enable payments')
                ->icon(Heroicon::OutlinedCheck)
                ->action('saveAndEnable'),
        ];
    }

    public function testConnection(): void
    {
        $this->persistCredentials();

        try {
            app(PayPalClient::class)->forgetToken();
            app(PayPalClient::class)->token();

            Notification::make()
                ->title('PayPal accepted these credentials')
                ->body('Press “Save and enable payments” to register the webhook and turn checkout on.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('PayPal rejected these credentials')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function saveAndEnable(): void
    {
        $this->persistCredentials();

        try {
            app(PayPalClient::class)->forgetToken();
            app(PayPalClient::class)->token();
        } catch (Throwable $e) {
            Notification::make()->title('PayPal rejected these credentials')
                ->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        // The step WooCommerce leaves to the owner. See §6.6.
        $result = app(PayPalWebhookService::class)->provision();

        Settings::set('paypal.enabled', $result->ok);

        if (! $result->ok) {
            Notification::make()
                ->title('Credentials saved, but the webhook is not registered')
                ->body($result->message)
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('PayPal is live')
            ->body('Your store can now accept payments. '.$result->message)
            ->success()
            ->send();
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function healthChecks(): array
    {
        return app(PayPalHealthCheck::class)->run();
    }

    public function maskedSecret(): ?string
    {
        $mode = PayPalMode::from((string) ($this->data['mode'] ?? 'sandbox'));

        return Settings::maskedHint($mode->settingKey('client_secret'));
    }

    private function loadCredentialsForMode(): void
    {
        $mode = PayPalMode::from((string) ($this->data['mode'] ?? 'sandbox'));

        $this->form->fill([
            'mode' => $mode->value,
            'client_id' => Settings::get($mode->settingKey('client_id')),
            'client_secret' => null,
        ]);
    }

    private function persistCredentials(): void
    {
        $state = $this->form->getState();
        $mode = PayPalMode::from((string) ($state['mode'] ?? 'sandbox'));

        Settings::set('paypal.mode', $mode->value);
        Settings::set($mode->settingKey('client_id'), $state['client_id'] ?? null);

        // An empty secret field means "keep what is saved", not "clear it".
        if (filled($state['client_secret'] ?? null)) {
            Settings::set($mode->settingKey('client_secret'), $state['client_secret']);
        }
    }
}
