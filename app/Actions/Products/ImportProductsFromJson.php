<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

/**
 * Bulk-creates products from a pasted JSON array.
 *
 * Artwork and sellable files cannot arrive this way, so every imported product
 * is forced to draft: a published product with no artwork is a broken page and
 * a broken sitemap entry. Status in the payload is ignored rather than
 * honoured, because an import that silently publishes 80 empty pages is worse
 * than one that refuses to.
 *
 * Validation runs over the whole payload before anything is written. A single
 * bad row aborts the import, so the catalog is never left half-populated with
 * the rest still to fix.
 */
final class ImportProductsFromJson
{
    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, array<string, int>> attribute key => value => id */
    private array $taxonomy = [];

    public function __invoke(string $json, string $locale = 'en'): ImportResult
    {
        $this->errors = [];

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ImportResult::failed(['That is not valid JSON: '.$e->getMessage()]);
        }

        // A single object is a reasonable thing to paste; wrap it.
        if (is_array($decoded) && ! array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        if (! is_array($decoded) || $decoded === []) {
            return ImportResult::failed(['Expected a JSON array with at least one product.']);
        }

        $this->loadTaxonomy();

        $rows = [];

        foreach ($decoded as $index => $item) {
            $position = (int) $index + 1;

            if (! is_array($item)) {
                $this->errors[] = "Item {$position}: expected an object.";

                continue;
            }

            $row = $this->validate($item, $position, $locale);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if ($this->errors !== []) {
            return ImportResult::failed($this->errors);
        }

        return $this->write($rows, $locale);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function validate(array $item, int $position, string $locale): ?array
    {
        $title = trim((string) ($item['title'] ?? ''));

        if ($title === '') {
            $this->errors[] = "Item {$position}: \"title\" is required.";

            return null;
        }

        $label = Str::limit($title, 40);

        // Prices are written as humans write them. Money does the conversion so
        // no float ever reaches the database.
        $rawPrice = $item['price'] ?? null;

        if ($rawPrice === null) {
            $this->errors[] = "{$label}: \"price\" is required.";

            return null;
        }

        try {
            $price = Money::fromDecimal(is_string($rawPrice) ? $rawPrice : number_format((float) $rawPrice, 2, '.', ''));
        } catch (\InvalidArgumentException) {
            $this->errors[] = "{$label}: \"price\" is not a valid amount.";

            return null;
        }

        $floor = (int) config('store.pricing.minimum_price_cents');

        if ($price->cents < $floor && $price->cents !== 0) {
            $this->errors[] = sprintf(
                '%s: price %s is below the %s minimum.',
                $label, $price->toDecimalString(), Money::fromCents($floor)->toDecimalString(),
            );

            return null;
        }

        $type = ProductType::tryFrom((string) ($item['type'] ?? 'single'));

        if ($type === null) {
            $this->errors[] = "{$label}: unknown type \"{$item['type']}\". Use single, mini_set, collection or bundle.";

            return null;
        }

        $slug = Str::slug($title);

        if (ProductTranslation::where('locale', $locale)->where('slug', $slug)->exists()) {
            $this->errors[] = "{$label}: a product with this name already exists.";

            return null;
        }

        return [
            'title' => $title,
            'subtitle' => $this->nullableString($item['subtitle'] ?? null),
            'description' => $this->nullableString($item['description'] ?? null),
            'slug' => $slug,
            'price' => $price,
            'type' => $type,
            'is_ai_generated' => (bool) ($item['is_ai_generated'] ?? true),
            'attributes' => $this->resolveAttributes($item, $label),
            'translations' => $this->resolveTranslations($item, $label, $locale),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<int>
     */
    private function resolveAttributes(array $item, string $label): array
    {
        $ids = [];

        foreach (Attribute::MANUAL as $key) {
            $given = $item[$key] ?? null;

            if ($given === null || $given === '') {
                continue;
            }

            foreach (is_array($given) ? $given : [$given] as $value) {
                $value = Str::slug((string) $value);
                $id = $this->taxonomy[$key][$value] ?? null;

                if ($id === null) {
                    // Auto-creating taxonomy from a typo quietly pollutes every
                    // filter and landing page, so an unknown value is an error.
                    $known = implode(', ', array_slice(array_keys($this->taxonomy[$key] ?? []), 0, 6));
                    $this->errors[] = "{$label}: unknown {$key} \"{$value}\". Known values include: {$known}…";

                    continue;
                }

                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, array<string, string|null>>
     */
    private function resolveTranslations(array $item, string $label, string $defaultLocale): array
    {
        $out = [];
        $given = $item['translations'] ?? [];

        if (! is_array($given)) {
            $this->errors[] = "{$label}: \"translations\" must be an object keyed by language.";

            return $out;
        }

        /** @var list<string> $locales */
        $locales = config('store.locales', ['en']);

        foreach ($given as $locale => $fields) {
            if (! in_array($locale, $locales, true)) {
                $this->errors[] = "{$label}: \"{$locale}\" is not a language this store uses.";

                continue;
            }

            if ($locale === $defaultLocale || ! is_array($fields)) {
                continue;
            }

            $title = trim((string) ($fields['title'] ?? ''));

            if ($title === '') {
                $this->errors[] = "{$label}: the {$locale} translation needs a title.";

                continue;
            }

            $out[$locale] = [
                'title' => $title,
                'subtitle' => $this->nullableString($fields['subtitle'] ?? null),
                'description' => $this->nullableString($fields['description'] ?? null),
            ];
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $rows */
    private function write(array $rows, string $defaultLocale): ImportResult
    {
        $created = 0;

        DB::transaction(function () use ($rows, $defaultLocale, &$created): void {
            foreach ($rows as $row) {
                /** @var Money $price */
                $price = $row['price'];

                $product = Product::create([
                    'type' => $row['type'],
                    'price_cents' => $price->cents,
                    'currency' => $price->currency,
                    // Forced, not taken from the payload. See the class docblock.
                    'status' => ProductStatus::Draft,
                    'is_ai_generated' => $row['is_ai_generated'],
                    'license_type' => 'personal',
                ]);

                $product->translations()->create([
                    'locale' => $defaultLocale,
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'],
                    'description' => $row['description'],
                    'slug' => $row['slug'],
                    'status' => ProductStatus::Draft->value,
                    'is_machine_translated' => false,
                    'reviewed_at' => now(),
                ]);

                foreach ($row['translations'] as $locale => $fields) {
                    $product->translations()->create([
                        'locale' => $locale,
                        'title' => $fields['title'],
                        'subtitle' => $fields['subtitle'],
                        'description' => $fields['description'],
                        'slug' => $this->uniqueSlug((string) $locale, Str::slug($fields['title'])),
                        'status' => ProductStatus::Draft->value,
                        'is_machine_translated' => false,
                        'reviewed_at' => now(),
                    ]);
                }

                if ($row['attributes'] !== []) {
                    $product->attributeValues()->sync($row['attributes']);
                }

                $created++;
            }
        });

        return ImportResult::succeeded($created);
    }

    private function uniqueSlug(string $locale, string $base): string
    {
        $base = $base !== '' ? $base : 'artwork';
        $slug = $base;
        $i = 2;

        while (ProductTranslation::where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function loadTaxonomy(): void
    {
        $this->taxonomy = [];

        foreach (Attribute::with('values')->whereIn('key', Attribute::MANUAL)->get() as $attribute) {
            $this->taxonomy[$attribute->key] = $attribute->values
                ->mapWithKeys(fn (AttributeValue $v): array => [$v->value => $v->id])
                ->all();
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** A working payload, used by the "show me the format" button. */
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
