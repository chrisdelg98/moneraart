<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

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
     * One button, because there is only one sensible thing to do here.
     *
     * Publishing from this page would offer to put a product in the shop with
     * no artwork and no files — the two things it cannot have yet, because
     * both need the record to exist. Publish lives on the edit page, after
     * there is something to publish.
     *
     * @return array<Action>
     */
    protected function getSaveActions(): array
    {
        return [
            ...array_filter(
                $this->defaultSaveActions(),
                fn (Action $action): bool => $action->getName() === 'cancel',
            ),

            Action::make('create')
                ->label('Save and add artwork')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->create()),
        ];
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
