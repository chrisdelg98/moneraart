<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AttributeValueTranslation;
use Illuminate\Database\Seeder;

/**
 * The six axes the catalog is organised by.
 *
 * Only style, room and theme are chosen by hand — ratio, orientation and colour
 * are derived from the uploaded files, so the product form shows three
 * dropdowns, not six. See §4.1 and §10.2.
 */
class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->axes() as $position => $axis) {
            $attribute = Attribute::updateOrCreate(
                ['key' => $axis['key']],
                [
                    'label' => $axis['label'],
                    'is_filterable' => $axis['filterable'],
                    // room and style carry the searches people actually type —
                    // "coffee bar wall art", "mid century kitchen art". Nobody
                    // searches for a 2:3 ratio.
                    'is_seo_landing' => $axis['seo_landing'],
                    'is_computed' => $axis['computed'],
                    'position' => $position,
                ],
            );

            foreach ($axis['values'] as $i => [$value, $en, $es]) {
                $attributeValue = AttributeValue::updateOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value],
                    ['position' => $i],
                );

                foreach ([['en', $en], ['es', $es]] as [$locale, $label]) {
                    AttributeValueTranslation::updateOrCreate(
                        ['attribute_value_id' => $attributeValue->id, 'locale' => $locale],
                        ['label' => $label, 'slug' => str($label)->slug()->toString()],
                    );
                }
            }
        }
    }

    /** @return list<array{key: string, label: string, filterable: bool, seo_landing: bool, computed: bool, values: list<array{0: string, 1: string, 2: string}>}> */
    private function axes(): array
    {
        return [
            [
                'key' => 'style', 'label' => 'Style',
                'filterable' => true, 'seo_landing' => true, 'computed' => false,
                'values' => [
                    ['mid-century', 'Mid-Century Modern', 'Mid-Century Modern'],
                    ['minimalist', 'Minimalist', 'Minimalista'],
                    ['abstract', 'Abstract', 'Abstracto'],
                    ['bohemian', 'Bohemian', 'Bohemio'],
                    ['art-deco', 'Art Deco', 'Art Déco'],
                    ['retro', 'Retro', 'Retro'],
                    ['line-art', 'Line Art', 'Arte lineal'],
                    ['vintage', 'Vintage', 'Vintage'],
                    ['scandinavian', 'Scandinavian', 'Escandinavo'],
                    ['botanical', 'Botanical', 'Botánico'],
                ],
            ],
            [
                'key' => 'room', 'label' => 'Room',
                'filterable' => true, 'seo_landing' => true, 'computed' => false,
                'values' => [
                    ['kitchen', 'Kitchen', 'Cocina'],
                    ['coffee-bar', 'Coffee Bar', 'Bar de café'],
                    ['living-room', 'Living Room', 'Sala'],
                    ['bedroom', 'Bedroom', 'Dormitorio'],
                    ['bathroom', 'Bathroom', 'Baño'],
                    ['dining-room', 'Dining Room', 'Comedor'],
                    ['office', 'Office', 'Oficina'],
                    ['nursery', 'Nursery', 'Cuarto infantil'],
                    ['entryway', 'Entryway', 'Recibidor'],
                    ['bar-cart', 'Bar Cart', 'Carrito de bar'],
                ],
            ],
            [
                'key' => 'theme', 'label' => 'Theme',
                'filterable' => true, 'seo_landing' => true, 'computed' => false,
                'values' => [
                    ['coffee', 'Coffee', 'Café'],
                    ['cocktails', 'Cocktails', 'Cócteles'],
                    ['nature', 'Nature', 'Naturaleza'],
                    ['geometric', 'Geometric', 'Geométrico'],
                    ['typography', 'Typography', 'Tipografía'],
                    ['music', 'Music', 'Música'],
                    ['travel', 'Travel', 'Viaje'],
                    ['food', 'Food', 'Comida'],
                    ['animals', 'Animals', 'Animales'],
                    ['celestial', 'Celestial', 'Celestial'],
                ],
            ],

            // ── Computed from the uploaded files. Never typed. ────────────
            [
                'key' => 'ratio', 'label' => 'Ratio',
                'filterable' => true, 'seo_landing' => false, 'computed' => true,
                'values' => [
                    ['2-3', '2:3', '2:3'],
                    ['3-4', '3:4', '3:4'],
                    ['4-5', '4:5', '4:5'],
                    ['1-1', '1:1', '1:1'],
                    ['a-series', 'A-Series', 'Serie A'],
                    ['11-14', '11×14', '11×14'],
                    ['16-20', '16×20', '16×20'],
                ],
            ],
            [
                // Distinct from ratio: ratio matters for the file, orientation
                // matters for browsing. Someone with a wide space above a sofa
                // filters by this, not by "3:4".
                'key' => 'orientation', 'label' => 'Orientation',
                'filterable' => true, 'seo_landing' => false, 'computed' => true,
                'values' => [
                    ['portrait', 'Portrait', 'Vertical'],
                    ['landscape', 'Landscape', 'Horizontal'],
                    ['square', 'Square', 'Cuadrado'],
                ],
            ],
            [
                'key' => 'color', 'label' => 'Colour',
                'filterable' => true, 'seo_landing' => false, 'computed' => true,
                'values' => [
                    ['amber', 'Amber', 'Ámbar'],
                    ['cream', 'Cream', 'Crema'],
                    ['terracotta', 'Terracotta', 'Terracota'],
                    ['sage', 'Sage Green', 'Verde salvia'],
                    ['navy', 'Navy', 'Azul marino'],
                    ['blush', 'Blush', 'Rosa palo'],
                    ['charcoal', 'Charcoal', 'Carbón'],
                    ['mustard', 'Mustard', 'Mostaza'],
                    ['walnut', 'Walnut', 'Nogal'],
                    ['monochrome', 'Black & White', 'Blanco y negro'],
                ],
            ],
        ];
    }
}
