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
use InvalidArgumentException;

/**
 * Validates and writes imported products, whatever parsed them.
 *
 * JSON and spreadsheets are two front doors to one room: both normalise their
 * input into the same shape and hand it here. Keeping the rules in one place is
 * the point — two importers with their own validation drift apart, and the one
 * used less often is the one that quietly accepts bad data.
 *
 * Artwork and sellable files cannot arrive through either door, so every
 * imported product is forced to draft. A status in the payload is ignored
 * rather than honoured: an import that silently publishes eighty empty pages is
 * worse than one that refuses to.
 */
final class ProductImporter
{
    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, array<string, int>> attribute key => value => id */
    private array $taxonomy = [];

    /** @var list<string> slugs claimed earlier in this same import */
    private array $claimedSlugs = [];

    /**
     * @param  list<array<string, mixed>>  $items  normalised rows
     * @param  list<string>  $parseErrors  problems the parser already found
     */
    public function import(array $items, string $locale, array $parseErrors = []): ImportResult
    {
        $this->errors = $parseErrors;
        $this->claimedSlugs = [];

        if ($items === [] && $this->errors === []) {
            return ImportResult::failed(['Nothing to import — no product rows were found.']);
        }

        $this->loadTaxonomy();

        $rows = [];

        foreach ($items as $index => $item) {
            $row = $this->validate($item, (int) $index + 1, $locale);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        // One bad row aborts everything. A half-populated catalog with the rest
        // still to fix is harder to recover from than a clear refusal.
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
            $this->errors[] = "Row {$position}: \"title\" is required.";

            return null;
        }

        $label = Str::limit($title, 40);
        $rawPrice = $item['price'] ?? null;

        if ($rawPrice === null || $rawPrice === '') {
            $this->errors[] = "{$label}: \"price\" is required.";

            return null;
        }

        try {
            // Prices are written as humans write them. Money converts, so no
            // float ever reaches the database.
            $price = Money::fromDecimal(
                is_string($rawPrice) ? trim($rawPrice) : number_format((float) $rawPrice, 2, '.', '')
            );
        } catch (InvalidArgumentException) {
            $this->errors[] = "{$label}: \"{$rawPrice}\" is not a valid price.";

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

        $rawType = trim((string) ($item['type'] ?? 'single'));
        $type = ProductType::tryFrom($rawType === '' ? 'single' : $rawType);

        if ($type === null) {
            $this->errors[] = "{$label}: unknown type \"{$rawType}\". Use single, mini_set, collection or bundle.";

            return null;
        }

        $slug = Str::slug($title);

        if (in_array($slug, $this->claimedSlugs, true)) {
            $this->errors[] = "{$label}: this name appears more than once in the file.";

            return null;
        }

        if (ProductTranslation::where('locale', $locale)->where('slug', $slug)->exists()) {
            $this->errors[] = "{$label}: a product with this name already exists.";

            return null;
        }

        $this->claimedSlugs[] = $slug;

        return [
            'title' => $title,
            'subtitle' => $this->nullableString($item['subtitle'] ?? null),
            'description' => $this->nullableString($item['description'] ?? null),
            'slug' => $slug,
            'price' => $price,
            'type' => $type,
            'is_ai_generated' => $this->boolish($item['is_ai_generated'] ?? true),
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

            // A spreadsheet cell holds "kitchen, coffee-bar"; JSON holds a list.
            $values = is_array($given)
                ? $given
                : (preg_split('/[,;|]/', (string) $given) ?: []);

            foreach ($values as $value) {
                $value = Str::slug(trim((string) $value));

                if ($value === '') {
                    continue;
                }

                $id = $this->taxonomy[$key][$value] ?? null;

                if ($id === null) {
                    // Creating taxonomy from a typo quietly pollutes every
                    // filter and landing page, so this is an error.
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
                // A row with only a translated description and no title is a
                // half-filled column, not a translation.
                if (filled($fields['subtitle'] ?? null) || filled($fields['description'] ?? null)) {
                    $this->errors[] = "{$label}: the {$locale} version needs a title.";
                }

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

    /** Spreadsheet cells carry "yes", "TRUE", 1 — all the same intent. */
    private function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            mb_strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'y', 'si', 'sí'],
            true,
        );
    }
}
