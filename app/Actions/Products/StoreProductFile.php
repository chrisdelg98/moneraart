<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Moves an uploaded sellable file onto the private disk and records what it is.
 *
 * Ratio, print size and DPI are read from the file rather than typed, which is
 * what lets the product form ask for three dropdowns instead of six. See §4.1.
 */
final class StoreProductFile
{
    /** Formats a customer can actually print from. */
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'application/zip' => 'zip',
    ];

    /** Common print ratios, as width ÷ height, with the tolerance we accept. */
    private const RATIOS = [
        '1:1' => 1.0,
        '2:3' => 0.6667,
        '3:4' => 0.75,
        '4:5' => 0.8,
        '3:2' => 1.5,
        '4:3' => 1.3333,
        '5:4' => 1.25,
        'A-series' => 0.7071,
    ];

    public function __invoke(Product $product, string $sourcePath, string $originalName): ProductFile
    {
        // The browser's Content-Type is a claim, not evidence. Sniff the file.
        $mime = $this->sniff($sourcePath);

        if (! isset(self::ALLOWED[$mime])) {
            throw new RuntimeException(
                "\"{$originalName}\" is a {$mime} file. Upload a PDF, JPG, PNG, SVG or ZIP."
            );
        }

        $checksum = hash_file('sha256', $sourcePath);

        if ($checksum === false) {
            throw new RuntimeException("\"{$originalName}\" could not be read.");
        }

        // The same bytes uploaded twice is a mistake, not two products' worth
        // of file. The unique index would catch it; this gives a clear message.
        $existing = $product->files()->where('checksum_sha256', $checksum)->first();

        if ($existing !== null) {
            throw new RuntimeException(
                "\"{$originalName}\" is already attached as \"{$existing->name()}\"."
            );
        }

        $uuid = (string) Str::uuid();
        $format = self::ALLOWED[$mime];
        $path = "{$product->uuid}/{$uuid}.{$format}";

        // Filenames are UUIDs, so a leaked path reveals nothing about the
        // catalog and nothing about what the file contains.
        Storage::disk('private')->put($path, file_get_contents($sourcePath));

        $dimensions = $this->dimensions($sourcePath, $mime);

        $file = $product->files()->create([
            'disk' => 'private',
            'path' => $path,
            'original_filename' => $originalName,
            'display_name' => pathinfo($originalName, PATHINFO_FILENAME),
            'mime_type' => $mime,
            'size_bytes' => filesize($sourcePath) ?: 0,
            'checksum_sha256' => $checksum,
            'format' => $format,
            'width_px' => $dimensions['width'],
            'height_px' => $dimensions['height'],
            'dpi' => $dimensions['dpi'],
            'ratio' => $dimensions['ratio'],
            'print_size' => $dimensions['print_size'],
            'position' => (int) $product->files()->max('position') + 1,
        ]);

        $this->refreshTotals($product);

        return $file;
    }

    /** Keeps the denormalised counters on the product honest. */
    private function refreshTotals(Product $product): void
    {
        $product->forceFill([
            'file_count' => $product->files()->count(),
            'total_bytes' => (int) $product->files()->sum('size_bytes'),
        ])->save();
    }

    /**
     * @return array{width: int|null, height: int|null, dpi: int|null, ratio: string|null, print_size: string|null}
     */
    private function dimensions(string $path, string $mime): array
    {
        $blank = ['width' => null, 'height' => null, 'dpi' => null, 'ratio' => null, 'print_size' => null];

        if (! str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
            return $blank;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return $blank;
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1) {
            return $blank;
        }

        $dpi = $this->readDpi($path);

        return [
            'width' => $width,
            'height' => $height,
            'dpi' => $dpi,
            'ratio' => $this->nearestRatio($width, $height),
            'print_size' => $this->printSize($width, $height, $dpi),
        ];
    }

    /** JPEG stores its density in the JFIF header; PNG in a pHYs chunk. */
    private function readDpi(string $path): ?int
    {
        $exif = @exif_read_data($path);

        if (is_array($exif) && isset($exif['XResolution'])) {
            $parts = explode('/', (string) $exif['XResolution']);
            $value = count($parts) === 2 && (float) $parts[1] !== 0.0
                ? (int) round((float) $parts[0] / (float) $parts[1])
                : (int) $parts[0];

            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    private function nearestRatio(int $width, int $height): ?string
    {
        $actual = $width / $height;
        $best = null;
        $closest = PHP_FLOAT_MAX;

        foreach (self::RATIOS as $label => $value) {
            $distance = abs($actual - $value);

            if ($distance < $closest) {
                $closest = $distance;
                $best = $label;
            }
        }

        // A panorama matches nothing sensibly; better no ratio than a wrong one.
        return $closest <= 0.03 ? $best : null;
    }

    /** Printable size at the file's own resolution, to the nearest inch. */
    private function printSize(int $width, int $height, ?int $dpi): ?string
    {
        $dpi ??= 300;

        if ($dpi < 72) {
            return null;
        }

        $w = (int) round($width / $dpi);
        $h = (int) round($height / $dpi);

        return $w > 0 && $h > 0 ? "{$w}x{$h}" : null;
    }

    private function sniff(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new RuntimeException('Could not inspect the uploaded file.');
        }

        // finfo objects are freed automatically; finfo_close() is deprecated
        // as of PHP 8.5.
        $mime = finfo_file($finfo, $path);

        return $mime !== false ? $mime : 'application/octet-stream';
    }
}
