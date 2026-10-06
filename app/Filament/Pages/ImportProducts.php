<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Products\ImportProductsFromJson;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Paste a JSON array, get drafts.
 *
 * Artwork and sellable files cannot arrive this way, so everything lands as a
 * draft and the uploads happen per product afterwards. That is the point: this
 * exists to get a catalog's worth of text in quickly, not to publish.
 *
 * `$form` is resolved by InteractsWithSchemas at runtime — declared here so the
 * magic property is visible to static analysis.
 *
 * @property-read Schema $form
 */
class ImportProducts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static ?string $navigationLabel = 'Import';

    protected static ?string $title = 'Import products';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.import-products';

    public ?string $payload = '';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->description('Paste a JSON array of products. Everything is created as a draft — add artwork and files afterwards.')
                    ->schema([
                        Textarea::make('payload')
                            ->label('JSON')
                            ->rows(20)
                            ->autosize(false)
                            ->extraInputAttributes(['class' => 'font-mono text-sm'])
                            ->placeholder(ImportProductsFromJson::sample()),
                    ]),
            ]);
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('loadSample')
                ->label('Load example')
                ->color('gray')
                ->action(fn () => $this->form->fill(['payload' => ImportProductsFromJson::sample()])),

            Action::make('import')
                ->label('Import')
                ->keyBindings(['mod+s'])
                ->action('import'),
        ];
    }

    public function import(ImportProductsFromJson $import): void
    {
        $payload = (string) ($this->form->getState()['payload'] ?? '');

        if (trim($payload) === '') {
            Notification::make()
                ->title('Nothing to import')
                ->body('Paste some JSON first, or load the example.')
                ->warning()
                ->send();

            return;
        }

        $result = $import($payload, (string) config('store.default_locale'));

        if (! $result->ok) {
            Notification::make()
                ->title($result->summary())
                // Every problem at once, so the payload is fixed in one pass
                // rather than one error per attempt.
                ->body(implode("\n", array_map(fn (string $e): string => '• '.$e, $result->errors)))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($result->summary())
            ->body('They are drafts until you add artwork and publish them.')
            ->success()
            ->send();

        $this->form->fill(['payload' => '']);
    }
}
