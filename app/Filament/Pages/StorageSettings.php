<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\ProductFile;
use App\Services\Storage\StorageHealthCheck;
use App\Services\Storage\StorageManager;
use App\Support\Facades\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Where product files live, and proof that they can.
 *
 * Switching driver does not move anything and does not need to: the disk is
 * recorded per file, so everything already sold keeps downloading from where it
 * was put while new uploads go to the new home. See §8.6.
 *
 * @property-read Schema $form
 */
class StorageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Storage';

    protected static ?string $title = 'Storage';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.storage-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'driver' => app(StorageManager::class)->driver(),
            'account_id' => Settings::get('storage.r2.account_id'),
            'access_key_id' => Settings::get('storage.r2.access_key_id'),
            // A stored secret is never rendered back to the browser.
            'secret_access_key' => null,
            'bucket' => Settings::get('storage.r2.bucket'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Where files are stored')
                ->description('Existing files are unaffected. Only new uploads go to the new location.')
                ->schema([
                    Radio::make('driver')
                        ->label('Storage')
                        ->options([
                            'local' => 'This server — simplest, included',
                            'r2' => 'Cloudflare R2 — no bandwidth charges',
                        ])
                        ->default('local')
                        ->live()
                        ->required(),
                ]),

            Section::make('Cloudflare R2')
                ->description('From your Cloudflare dashboard, under R2.')
                ->visible(fn (Get $get): bool => $get('driver') === 'r2')
                ->columns(2)
                ->schema([
                    TextInput::make('account_id')
                        ->label('Account ID')
                        ->autocomplete(false)
                        ->maxLength(191)
                        ->helperText('The long hex string in your R2 endpoint URL.'),

                    TextInput::make('bucket')
                        ->label('Bucket name')
                        ->autocomplete(false)
                        ->maxLength(191),

                    TextInput::make('access_key_id')
                        ->label('Access key ID')
                        ->autocomplete(false)
                        ->maxLength(191),

                    TextInput::make('secret_access_key')
                        ->label('Secret access key')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->maxLength(191)
                        ->placeholder(fn (): string => Settings::get('storage.r2.secret_access_key') ? '••••••••' : 'Not set')
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
                ->label('Save')
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),
        ];
    }

    /**
     * Writes the credentials, then runs the checks against them.
     *
     * The driver itself is left alone: this answers "would R2 work?" without
     * sending a single new upload there until the answer is yes.
     */
    public function testConnection(): void
    {
        $this->persistCredentials();

        $previous = app(StorageManager::class)->driver();
        Settings::set('storage.driver', $this->data['driver'] ?? 'local');

        app(StorageManager::class)->configure();
        $checks = app(StorageHealthCheck::class)->run();

        Settings::set('storage.driver', $previous);

        $failed = array_values(array_filter($checks, fn (array $c): bool => ! $c['ok']));

        if ($failed === []) {
            Notification::make()
                ->title('Storage is ready')
                ->body('Press Save to send new uploads there.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title($failed[0]['label'].' — failed')
            ->body($failed[0]['detail'])
            ->danger()
            ->persistent()
            ->send();
    }

    public function save(): void
    {
        $this->persistCredentials();

        $driver = (string) ($this->data['driver'] ?? 'local');

        if ($driver !== 'local') {
            app(StorageManager::class)->configure();

            // Refusing here is the whole point of the button above: a driver
            // saved without working means the next upload is lost quietly.
            foreach (app(StorageHealthCheck::class)->run() as $check) {
                if ($check['ok']) {
                    continue;
                }

                Notification::make()
                    ->title('Not saved — '.$check['label'].' failed')
                    ->body($check['detail'])
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        }

        Settings::set('storage.driver', $driver);

        Notification::make()
            ->title('New uploads will go to '.($driver === 'local' ? 'this server' : 'Cloudflare R2'))
            ->body('Files already uploaded keep downloading from where they are.')
            ->success()
            ->send();
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function healthChecks(): array
    {
        return app(StorageHealthCheck::class)->run();
    }

    /** @return array<string, int> files per disk, so a split is visible */
    public function fileCounts(): array
    {
        return ProductFile::query()
            ->selectRaw('disk, count(*) as total')
            ->groupBy('disk')
            ->pluck('total', 'disk')
            ->all();
    }

    private function persistCredentials(): void
    {
        $data = $this->form->getState();

        Settings::set('storage.r2.account_id', $data['account_id'] ?? null);
        Settings::set('storage.r2.access_key_id', $data['access_key_id'] ?? null);
        Settings::set('storage.r2.bucket', $data['bucket'] ?? null);

        // An empty box means "keep what is saved", not "erase it".
        if (filled($data['secret_access_key'] ?? null)) {
            Settings::set('storage.r2.secret_access_key', $data['secret_access_key']);
        }
    }
}
