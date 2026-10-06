<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A public preview — capped resolution, watermarked at large sizes, never the
 * sellable file. See §10.1.
 *
 * @property array<string, array<string, string>>|null $variants
 * @property array<string, string>|null $alt_text
 */
class ProductImage extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'alt_text' => 'array',
            'is_cover' => 'boolean',
            'is_mockup' => 'boolean',
            'watermarked' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function alt(?string $locale = null): string
    {
        return $this->alt_text[$locale ?? app()->getLocale()] ?? '';
    }

    /** Width/height are stored so the markup can prevent layout shift. */
    public function aspectRatio(): float
    {
        return $this->height > 0 ? $this->width / $this->height : 1.0;
    }

    public function orientation(): string
    {
        $ratio = $this->aspectRatio();

        return match (true) {
            $ratio > 1.05 => 'landscape',
            $ratio < 0.95 => 'portrait',
            default => 'square',
        };
    }

    /** @return array<int, string>  width => url */
    public function variantUrls(string $format = 'webp'): array
    {
        return collect($this->variants[$format] ?? [])
            // Keys are ints in memory and strings after a JSON round trip.
            ->mapWithKeys(fn (string $path, int|string $w): array => [
                (int) $w => Storage::disk($this->disk)->url($path),
            ])
            ->all();
    }

    public function srcset(string $format = 'webp'): string
    {
        return collect($this->variantUrls($format))
            ->map(fn (string $url, int $w): string => "{$url} {$w}w")
            ->implode(', ');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path_original);
    }
}
