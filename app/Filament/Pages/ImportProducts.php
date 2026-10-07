<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Products\GenerateProductTemplate;
use App\Actions\Products\ImportProductsFromJson;
use App\Actions\Products\ImportProductsFromSpreadsheet;
use App\Actions\Products\ImportResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Two ways in: paste JSON, or fill in the spreadsheet template.
 *
 * Both end at the same validator. Artwork and sellable files cannot arrive
 * either way, so everything lands as a draft and the uploads happen per
 * product afterwards.
 *
 * `$form` is resolved by InteractsWithSchemas at runtime — declared here so the
 * magic property is visible to static analysis.
 *
 * @property-read Schema $form
 */
class ImportProducts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import';

    protected static ?string $title = 'Import products';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.import-products';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->columnSpanFull()->tabs([
                    Tab::make('Spreadsheet')
                        ->icon(Heroicon::OutlinedTableCells)
                        ->schema([
                            FileUpload::make('spreadsheet')
                                ->label('Filled-in template')
                                ->acceptedFileTypes([
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'application/vnd.ms-excel',
                                    'text/csv',
                                ])
                                ->maxSize(10240)
                                ->disk('local')
                                ->directory('imports')
                                ->helperText('Download the template first — its dropdowns keep the style, room and theme values valid.'),

                            SchemaActions::make([
                                Action::make('importSpreadsheet')
                                    ->label('Import spreadsheet')
                                    ->icon(Heroicon::OutlinedTableCells)
                                    ->action('importSpreadsheet'),
                            ]),
                        ]),

                    Tab::make('JSON')
                        ->icon(Heroicon::OutlinedCodeBracket)
                        ->schema([
                            Textarea::make('payload')
                                ->label('JSON')
                                ->rows(18)
                                ->autosize(false)
                                ->extraInputAttributes(['class' => 'font-mono text-sm'])
                                ->placeholder(ImportProductsFromJson::sample()),

                            SchemaActions::make([
                                Action::make('importJson')
                                    ->label('Import JSON')
                                    ->icon(Heroicon::OutlinedCodeBracket)
                                    ->action('importJson'),

                                Action::make('loadSample')
                                    ->label('Load example')
                                    ->color('gray')
                                    ->link()
                                    ->action('loadSample'),
                            ]),
                        ]),
                ]),
            ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download template')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => response()->streamDownload(
                    fn () => print app(GenerateProductTemplate::class)(),
                    GenerateProductTemplate::filename(),
                )),
        ];
    }

    public function importSpreadsheet(): void
    {
        $import = app(ImportProductsFromSpreadsheet::class);

        $uploaded = $this->form->getState()['spreadsheet'] ?? null;
        $path = is_array($uploaded) ? reset($uploaded) : $uploaded;

        if (! is_string($path) || $path === '') {
            $this->warn('No file chosen', 'Pick your filled-in template first.');

            return;
        }

        $absolute = Storage::disk('local')->path($path);
        $result = $import($absolute, (string) config('store.default_locale'));

        // The upload is working material, not something to keep. Remove it
        // either way so failed attempts do not pile up on disk.
        Storage::disk('local')->delete($path);

        $this->report($result, clearField: 'spreadsheet');
    }

    public function importJson(): void
    {
        $import = app(ImportProductsFromJson::class);

        $payload = (string) ($this->form->getState()['payload'] ?? '');

        if (trim($payload) === '') {
            $this->warn('Nothing to import', 'Paste some JSON first, or load the example.');

            return;
        }

        $this->report(
            $import($payload, (string) config('store.default_locale')),
            clearField: 'payload',
        );
    }

    public function loadSample(): void
    {
        $this->form->fill([...$this->data, 'payload' => ImportProductsFromJson::sample()]);
    }

    private function report(ImportResult $result, string $clearField): void
    {
        if (! $result->ok) {
            Notification::make()
                ->title($result->summary())
                // Every problem at once, so the file is fixed in one pass
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

        $this->form->fill([...$this->data, $clearField => $clearField === 'payload' ? '' : null]);
    }

    private function warn(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->warning()->send();
    }
}
