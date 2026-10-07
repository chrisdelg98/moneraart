<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Products\StoreProductFile;
use App\Actions\Products\SyncComputedAttributes;
use App\Models\Product;
use App\Models\ProductFile;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The sellable files.
 *
 * Uploads go to a temporary location, then StoreProductFile moves them onto the
 * private disk and reads their dimensions, ratio and DPI — which is what keeps
 * the product form down to three dropdowns instead of six.
 *
 * A relation manager rather than a field on the form: files exist per product,
 * are added and removed one at a time, and must not be re-saved every time
 * someone edits a price.
 */
class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files';

    protected static ?string $modelLabel = 'file';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('display_name')
                ->label('Name shown to the customer')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('display_name')
                    ->label('File')
                    ->getStateUsing(fn (ProductFile $r): string => $r->name())
                    ->description(fn (ProductFile $r): string => $r->original_filename)
                    ->wrap(),

                TextColumn::make('format')->badge(),

                TextColumn::make('ratio')
                    ->label('Ratio')
                    ->placeholder('—')
                    ->description(fn (ProductFile $r): ?string => $r->print_size !== null ? $r->print_size.' in' : null),

                TextColumn::make('dpi')->label('DPI')->placeholder('—')->alignCenter(),

                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->getStateUsing(fn (ProductFile $r): string => $r->humanSize())
                    ->alignEnd(),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Add files')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Product files')
                            ->multiple()
                            ->disk('local')
                            ->directory('uploads')
                            ->maxSize(204800)
                            ->helperText('PDF, JPG, PNG, SVG or ZIP. Up to 200 MB each. Ratio and DPI are read from the file.'),
                    ])
                    ->action(fn (array $data) => $this->storeUploads($data)),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    // The admin's own copy, not a customer grant — no token,
                    // no counter, and it never leaves the admin panel.
                    ->action(fn (ProductFile $record) => Storage::disk($record->disk)
                        ->download($record->path, $record->original_filename)),

                DeleteAction::make()
                    ->after(fn () => $this->afterChange()),
            ])
            ->emptyStateHeading('No files yet')
            ->emptyStateDescription('These are what the customer downloads after paying.');
    }

    /** @param array<string, mixed> $data */
    private function storeUploads(array $data): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();
        $store = app(StoreProductFile::class);

        $uploaded = $data['files'] ?? [];
        $stored = 0;
        $problems = [];

        foreach (is_array($uploaded) ? $uploaded : [$uploaded] as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $absolute = Storage::disk('local')->path($path);
            $name = basename($path);

            try {
                $store($product, $absolute, $name);
                $stored++;
            } catch (RuntimeException $e) {
                $problems[] = $e->getMessage();
            } finally {
                // The temporary upload is working material either way.
                Storage::disk('local')->delete($path);
            }
        }

        if ($stored > 0) {
            $this->afterChange();

            Notification::make()
                ->title($stored === 1 ? '1 file added' : "{$stored} files added")
                ->success()
                ->send();
        }

        if ($problems !== []) {
            Notification::make()
                ->title('Some files were not added')
                ->body(implode("\n", array_map(fn (string $p): string => '• '.$p, $problems)))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /** Ratio and orientation come from the files, so they change with them. */
    private function afterChange(): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $product->load('files');
        $product->forceFill([
            'file_count' => $product->files->count(),
            'total_bytes' => (int) $product->files->sum('size_bytes'),
        ])->save();

        app(SyncComputedAttributes::class)($product);
    }
}
