<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Products\SyncComputedAttributes;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImagePipeline;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The public preview images.
 *
 * Each upload is re-encoded, capped, stripped of metadata and fanned out into
 * WebP and AVIF variants by ImagePipeline. These are never the files the
 * customer buys — that is the Files manager. See §10.1.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Artwork';

    protected static ?string $modelLabel = 'image';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alt_en')
                ->label('Alt text (EN)')
                ->maxLength(255)
                ->helperText('Describes the image for screen readers and image search. Generated if left empty.'),

            TextInput::make('alt_es')
                ->label('Alt text (ES)')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path_original')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('path_original')
                    ->label('Preview')
                    ->disk('public')
                    ->height(72),

                TextColumn::make('dimensions')
                    ->label('Size')
                    ->getStateUsing(fn (ProductImage $r): string => "{$r->width} × {$r->height}")
                    ->description(fn (ProductImage $r): string => $r->orientation()),

                TextColumn::make('dominant_color')
                    ->label('Colour')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('variants')
                    ->label('Variants')
                    ->getStateUsing(fn (ProductImage $r): string => count($r->variants['webp'] ?? []).' sizes')
                    ->description(fn (ProductImage $r): string => $r->watermarked ? 'watermarked' : 'no watermark'),

                TextColumn::make('is_cover')
                    ->label('Cover')
                    ->badge()
                    ->getStateUsing(fn (ProductImage $r): string => $r->is_cover ? 'Cover' : '')
                    ->color('success'),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Add artwork')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Images')
                            ->image()
                            ->multiple()
                            ->disk('local')
                            ->directory('uploads')
                            ->maxSize(51200)
                            ->helperText('JPG or PNG, up to 50 MP. Variants and the watermark are generated for you.'),
                    ])
                    ->action(fn (array $data) => $this->storeUploads($data)),
            ])
            ->recordActions([
                Action::make('makeCover')
                    ->label('Make cover')
                    ->icon('heroicon-o-star')
                    ->color('gray')
                    ->visible(fn (ProductImage $record): bool => ! $record->is_cover)
                    ->action(fn (ProductImage $record) => $this->setCover($record)),

                EditAction::make()
                    ->fillForm(fn (ProductImage $record): array => [
                        'alt_en' => $record->alt_text['en'] ?? null,
                        'alt_es' => $record->alt_text['es'] ?? null,
                    ])
                    ->using(function (ProductImage $record, array $data): ProductImage {
                        $record->alt_text = array_filter([
                            'en' => $data['alt_en'] ?? null,
                            'es' => $data['alt_es'] ?? null,
                        ]);
                        $record->save();

                        return $record;
                    }),

                DeleteAction::make()
                    ->before(fn (ProductImage $record) => $this->deleteDerivatives($record))
                    ->after(fn () => $this->afterChange()),
            ])
            ->emptyStateHeading('No artwork yet')
            ->emptyStateDescription('This is what customers see. The first image becomes the cover.');
    }

    /** @param array<string, mixed> $data */
    private function storeUploads(array $data): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();
        $pipeline = app(ImagePipeline::class);

        $uploaded = $data['images'] ?? [];
        $stored = 0;
        $problems = [];

        foreach (is_array($uploaded) ? $uploaded : [$uploaded] as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            try {
                // The first image on a product with no cover becomes the cover.
                $isCover = $product->cover_image_id === null && $stored === 0;

                $pipeline->process($product, Storage::disk('local')->path($path), $isCover);
                $stored++;
            } catch (RuntimeException $e) {
                $problems[] = basename($path).': '.$e->getMessage();
            } finally {
                Storage::disk('local')->delete($path);
            }
        }

        if ($stored > 0) {
            $this->afterChange();

            Notification::make()
                ->title($stored === 1 ? '1 image added' : "{$stored} images added")
                ->success()
                ->send();
        }

        if ($problems !== []) {
            Notification::make()
                ->title('Some images were not added')
                ->body(implode("\n", array_map(fn (string $p): string => '• '.$p, $problems)))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private function setCover(ProductImage $image): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $product->images()->update(['is_cover' => false]);
        $image->forceFill(['is_cover' => true])->save();
        $product->forceFill(['cover_image_id' => $image->id])->save();
    }

    /** Derived files are regenerable, but leaving them orphaned wastes the disk. */
    private function deleteDerivatives(ProductImage $image): void
    {
        $disk = Storage::disk($image->disk);
        $disk->delete($image->path_original);

        foreach ($image->variants ?? [] as $paths) {
            foreach ($paths as $path) {
                $disk->delete($path);
            }
        }
    }

    /** Orientation and colour come from the artwork, so they change with it. */
    private function afterChange(): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $product->load('images');

        // A deleted cover leaves a dangling reference; promote the next image.
        if ($product->cover_image_id !== null && ! $product->images->contains('id', $product->cover_image_id)) {
            $next = $product->images->first();
            $product->forceFill(['cover_image_id' => $next?->id])->save();
            $next?->forceFill(['is_cover' => true])->save();
        }

        app(SyncComputedAttributes::class)($product);
    }
}
