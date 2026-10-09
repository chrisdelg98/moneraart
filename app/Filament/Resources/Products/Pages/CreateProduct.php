<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Products\Pages\Concerns\HandlesProductTranslation;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use HandlesProductTranslation;
    use PutsFormActionsInHeader;

    protected static string $resource = ProductResource::class;

    /** @var array{translation: array<string, mixed>, attributes: array<string, mixed>} */
    private array $extracted = ['translation' => [], 'attributes' => []];

    /**
     * Two buttons instead of one, because the choice is already being made.
     *
     * The status select still exists for the cases the buttons do not cover —
     * scheduling, archiving — but the two answers anyone gives ninety per cent
     * of the time should not need a dropdown first.
     *
     * @return array<Action>
     */
    protected function getSaveActions(): array
    {
        return [
            Action::make('saveAsDraft')
                ->label('Save as draft')
                ->color('gray')
                ->action(fn () => $this->createWithStatus(ProductStatus::Draft)),

            Action::make('publish')
                ->label('Publish product')
                ->action(fn () => $this->createWithStatus(ProductStatus::Published)),
        ];
    }

    private function createWithStatus(ProductStatus $status): void
    {
        $this->data['status'] = $status->value;

        $this->create();
    }

    /**
     * Straight to the edit page, which is where the artwork and the files are.
     *
     * Filament's relation managers need a saved record, so on this page there
     * is nothing to upload to yet. Landing on the index instead would leave a
     * product with no image and no hint of where to add one.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->extracted = $this->pullNonProductFields($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Product $product */
        $product = $this->record;

        $this->saveTranslationAndAttributes($product, $this->extracted);
    }
}
