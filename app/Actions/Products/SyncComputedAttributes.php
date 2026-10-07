<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Str;

/**
 * Derives ratio, orientation and colour from what was uploaded.
 *
 * These three axes are why the product form asks for three dropdowns instead
 * of six: nobody types them. They are recomputed whenever files or artwork
 * change, and the administrator's own three choices are left untouched.
 *
 * See §4.1 and §10.2.
 */
final class SyncComputedAttributes
{
    /**
     * Hue ranges mapped to the palette the store browses by. Approximate on
     * purpose — this drives a filter, not a colour-management pipeline.
     *
     * @var list<array{0: string, 1: int, 2: int}> name, hue from, hue to
     */
    private const HUES = [
        ['terracotta', 5, 20],
        ['amber', 21, 45],
        ['mustard', 46, 60],
        ['sage', 61, 160],
        ['navy', 161, 260],
        ['blush', 261, 345],
        ['terracotta', 346, 360],
    ];

    public function __invoke(Product $product): void
    {
        $product->loadMissing(['files', 'images', 'attributeValues.attribute']);

        $values = [
            'ratio' => $this->ratios($product),
            'orientation' => $this->orientations($product),
            'color' => $this->colors($product),
        ];

        $ids = [];

        foreach ($values as $key => $slugs) {
            foreach ($slugs as $slug) {
                $id = $this->resolve($key, $slug);

                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        // Only the computed axes are replaced. Syncing everything would wipe
        // the style, room and theme the administrator chose by hand.
        $manual = $product->attributeValues
            ->filter(fn (AttributeValue $v): bool => in_array($v->attribute->key, Attribute::MANUAL, true))
            ->pluck('id')
            ->all();

        $product->attributeValues()->sync(array_values(array_unique([...$manual, ...$ids])));
        $product->load('attributeValues.attribute');
    }

    /** @return list<string> */
    private function ratios(Product $product): array
    {
        return $product->files
            ->pluck('ratio')
            ->filter()
            // Str::slug('2:3') yields '23' — the colon has to become the dash
            // the taxonomy stores before slugging.
            ->map(fn (string $r): string => Str::slug(str_replace(':', '-', $r)))
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function orientations(Product $product): array
    {
        // toBase(): mapping an Eloquent collection keeps it an Eloquent
        // collection, and merging strings into one makes it look for models.
        $fromImages = $product->images
            ->map(fn (ProductImage $i): string => $i->orientation())
            ->toBase();

        $fromFiles = $product->files
            ->filter(fn ($f): bool => $f->width_px > 0 && $f->height_px > 0)
            ->map(function ($f): string {
                $ratio = $f->width_px / $f->height_px;

                return match (true) {
                    $ratio > 1.05 => 'landscape',
                    $ratio < 0.95 => 'portrait',
                    default => 'square',
                };
            });

        return $fromImages->merge($fromFiles->toBase())->unique()->values()->all();
    }

    /**
     * Taken from the preview images, which is where the pipeline already
     * extracted a dominant colour for the loading placeholder.
     *
     * @return list<string>
     */
    private function colors(Product $product): array
    {
        return $product->images
            ->pluck('dominant_color')
            ->filter()
            ->map(fn (string $hex): ?string => $this->nameFor($hex))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function nameFor(string $hex): ?string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return null;
        }

        [$r, $g, $b] = array_map(
            fn (string $pair): int => (int) hexdec($pair),
            str_split($hex, 2),
        );

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        // Near-grey has no meaningful hue, so it is named by lightness instead.
        if ($delta < 25) {
            return match (true) {
                $max > 225 => 'cream',
                $max < 70 => 'charcoal',
                default => 'monochrome',
            };
        }

        $hue = match ($max) {
            $r => 60 * fmod((($g - $b) / $delta), 6),
            $g => 60 * ((($b - $r) / $delta) + 2),
            default => 60 * ((($r - $g) / $delta) + 4),
        };

        if ($hue < 0) {
            $hue += 360;
        }

        $hue = (int) round($hue);

        // Dark warm tones read as walnut rather than as their hue.
        if ($max < 110 && $hue >= 5 && $hue <= 45) {
            return 'walnut';
        }

        foreach (self::HUES as [$name, $from, $to]) {
            if ($hue >= $from && $hue <= $to) {
                return $name;
            }
        }

        return null;
    }

    private function resolve(string $attributeKey, string $slug): ?int
    {
        return AttributeValue::query()
            ->whereHas('attribute', fn ($q) => $q->where('key', $attributeKey))
            ->where('value', $slug)
            ->value('id');
    }
}
