<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages\Concerns;

use App\Models\AttributeValue;
use Illuminate\Support\Str;

/**
 * Moves the two labels between the form and the translations table.
 *
 * They are rows in attribute_value_translations, not columns here, so the
 * form carries label_en and label_es and this puts them where they live.
 */
trait HandlesCategoryLabels
{
    /** @var array<string, string|null> */
    private array $labels = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullLabels(array $data): array
    {
        $this->labels = [
            'en' => $data['label_en'] ?? null,
            // Empty Spanish is not an empty label: it falls back to the
            // English one, which beats a blank filter in the shop.
            'es' => filled($data['label_es'] ?? null) ? $data['label_es'] : ($data['label_en'] ?? null),
        ];

        unset($data['label_en'], $data['label_es']);

        return $data;
    }

    protected function saveLabels(AttributeValue $value): void
    {
        foreach ($this->labels as $locale => $label) {
            if (blank($label)) {
                continue;
            }

            $value->translations()->updateOrCreate(
                ['locale' => $locale],
                ['label' => $label, 'slug' => Str::slug((string) $label)],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillLabels(array $data, AttributeValue $value): array
    {
        $value->loadMissing('translations');

        $data['label_en'] = $value->translations->firstWhere('locale', 'en')?->label;
        $data['label_es'] = $value->translations->firstWhere('locale', 'es')?->label;

        return $data;
    }
}
