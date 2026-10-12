<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\HandlesCategoryLabels;
use App\Models\AttributeValue;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use HandlesCategoryLabels;
    use PutsFormActionsInHeader;

    protected static string $resource = CategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Last, so a new category never jumps ahead of the ones already there.
        $data['position'] = (int) AttributeValue::where('attribute_id', $data['attribute_id'])
            ->max('position') + 1;

        return $this->pullLabels($data);
    }

    protected function afterCreate(): void
    {
        /** @var AttributeValue $value */
        $value = $this->record;

        $this->saveLabels($value);
    }
}
