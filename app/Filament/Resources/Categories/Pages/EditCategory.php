<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\PutsFormActionsInHeader;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\HandlesCategoryLabels;
use App\Models\AttributeValue;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    use HandlesCategoryLabels;
    use PutsFormActionsInHeader;

    protected static string $resource = CategoryResource::class;

    /** @return array<Action> */
    protected function getRecordActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var AttributeValue $value */
        $value = $this->record;

        return $this->fillLabels($data, $value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullLabels($data);
    }

    protected function afterSave(): void
    {
        /** @var AttributeValue $value */
        $value = $this->record;

        $this->saveLabels($value);
    }
}
