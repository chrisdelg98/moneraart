<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Products\Pages\Concerns\HandlesProductTranslation;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use HandlesProductTranslation;
    use PutsFormActionsInHeader;

    protected static string $resource = ProductResource::class;

    /** @var array{translation: array<string, mixed>, attributes: array<string, mixed>} */
    private array $extracted = ['translation' => [], 'attributes' => []];

    /** @return array<Action> */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Save, plus a one-press publish while the product is still a draft.
     *
     * The alternative is: change the select, then press save. Two steps for
     * the single most common thing done on this page.
     *
     * @return array<Action>
     */
    protected function getSaveActions(): array
    {
        /** @var Product $product */
        $product = $this->record;

        $actions = $this->defaultSaveActions();

        if ($product->status !== ProductStatus::Draft) {
            return $actions;
        }

        $actions[] = Action::make('publish')
            ->label('Publish product')
            ->requiresConfirmation()
            ->modalHeading('Publish this product?')
            ->modalDescription(fn (): string => $product->files()->count() === 0
                ? 'There are no files attached yet, so it will show as "Soon" and cannot be bought.'
                : 'It will appear in the shop straight away.')
            ->action(function (): void {
                $this->data['status'] = ProductStatus::Published->value;
                $this->save();
            });

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->record;

        return $this->fillNonProductFields($data, $product);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->extracted = $this->pullNonProductFields($data);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Product $product */
        $product = $this->record;

        $this->saveTranslationAndAttributes($product, $this->extracted);
    }
}
