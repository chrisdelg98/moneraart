<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Products\Pages\Concerns\HandlesProductTranslation;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use HandlesProductTranslation;
    use PutsFormActionsInHeader;

    protected static string $resource = ProductResource::class;

    /** @var array{translation: array<string, mixed>, attributes: array<string, mixed>} */
    private array $extracted = ['translation' => [], 'attributes' => []];

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
