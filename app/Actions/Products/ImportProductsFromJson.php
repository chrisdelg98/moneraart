<?php

declare(strict_types=1);

namespace App\Actions\Products;

use JsonException;

/**
 * Parses a pasted JSON array and hands it to the shared importer.
 *
 * This class only knows about JSON. Every rule about what makes a product valid
 * lives in ProductImporter, so the two import routes cannot drift apart.
 */
final readonly class ImportProductsFromJson
{
    public function __construct(private ProductImporter $importer) {}

    public function __invoke(string $json, string $locale = 'en'): ImportResult
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ImportResult::failed(['That is not valid JSON: '.$e->getMessage()]);
        }

        // A single object is a reasonable thing to paste; wrap it.
        if (is_array($decoded) && $decoded !== [] && ! array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        if (! is_array($decoded) || $decoded === []) {
            return ImportResult::failed(['Expected a JSON array with at least one product.']);
        }

        $items = [];
        $errors = [];

        foreach ($decoded as $index => $item) {
            if (! is_array($item)) {
                $errors[] = 'Item '.((int) $index + 1).': expected an object.';

                continue;
            }

            $items[] = $item;
        }

        return $this->importer->import($items, $locale, $errors);
    }

    /** A working payload, used by the "load example" button. */
    public static function sample(): string
    {
        return json_encode([
            [
                'title' => 'Mid-Century Modern Coffee Bar Wall Art',
                'subtitle' => 'Warm retro tones for a kitchen nook',
                'description' => 'A set of warm, muted prints for a coffee corner.',
                'price' => '5.99',
                'type' => 'single',
                'style' => 'mid-century',
                'room' => ['kitchen', 'coffee-bar'],
                'theme' => 'coffee',
                'translations' => [
                    'es' => [
                        'title' => 'Arte mural de bar de café mid-century',
                        'subtitle' => 'Tonos cálidos retro para un rincón de cocina',
                        'description' => 'Un conjunto de láminas cálidas para un rincón de café.',
                    ],
                ],
            ],
            [
                'title' => 'Minimalist Botanical Set',
                'price' => '12.99',
                'type' => 'mini_set',
                'style' => 'minimalist',
                'room' => 'living-room',
                'theme' => 'nature',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}
