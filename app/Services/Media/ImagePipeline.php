<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\Unit;
use Spatie\Image\Image;

/**
 * Turns one uploaded original into the public preview set.
 *
 * Two classes of image exist and are never mixed: a preview is capped,
 * stripped and watermarked; the sellable file lives on the private disk and
 * never passes through here. See §10.1.
 */
final class ImagePipeline
{
    /** Widths generated for srcset. */
    public const WIDTHS = [320, 640, 960, 1280, 1920];

    /** Widths large enough to be worth stealing, so they carry the mark. */
    private const WATERMARK_FROM = 1280;

    /**
     * Memory in GD scales with pixel count: a 6000x4000 raster is 96 MB before
     * any work begins, and an 8000x8000 upload needs ~256 MB for the source
     * alone. This cap is a denial-of-service control, not a style rule, and it
     * is checked from the header before the file is ever decoded. See §10.2.1.
     */
    public const MAX_MEGAPIXELS = 50;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @param  string  $sourcePath  absolute path to the uploaded original
     */
    public function process(Product $product, string $sourcePath, bool $isCover = false): ProductImage
    {
        [$width, $height] = $this->assertSafeDimensions($sourcePath);

        $uuid = (string) Str::uuid();
        $dir = "catalog/{$product->uuid}";
        $disk = Storage::disk('public');

        $disk->makeDirectory($dir);

        // The original is re-encoded rather than copied. Re-encoding destroys
        // any payload embedded in the uploaded bytes and strips EXIF, which
        // can carry the GPS coordinates of wherever the file was made.
        $originalPath = "{$dir}/{$uuid}.webp";
        Image::load($sourcePath)
            ->format('webp')
            ->quality(90)
            ->save($disk->path($originalPath));

        $image = new ProductImage([
            'product_id' => $product->id,
            'disk' => 'public',
            'path_original' => $originalPath,
            'width' => $width,
            'height' => $height,
            'dominant_color' => $this->dominantColor($sourcePath),
            'is_cover' => $isCover,
            'watermarked' => false,
            'variants' => [],
        ]);

        $image->save();

        $image->variants = $this->generateVariants($sourcePath, $dir, $uuid, $width);
        $image->watermarked = $this->watermarkEnabled();
        $image->save();

        if ($isCover) {
            $product->forceFill(['cover_image_id' => $image->id])->save();
        }

        return $image;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function generateVariants(string $source, string $dir, string $uuid, int $sourceWidth): array
    {
        $disk = Storage::disk('public');
        $variants = ['webp' => [], 'avif' => []];

        foreach (self::WIDTHS as $targetWidth) {
            // Never upscale. A 640px original gains nothing from a 1920 variant
            // but costs the bandwidth of one.
            if ($targetWidth > $sourceWidth) {
                continue;
            }

            foreach (['webp' => 82, 'avif' => 55] as $format => $quality) {
                $path = "{$dir}/{$uuid}-{$targetWidth}.{$format}";

                $image = Image::load($source)
                    ->fit(Fit::Max, $targetWidth, $targetWidth * 4)
                    ->format($format)
                    ->quality($quality);

                if ($this->shouldWatermark($targetWidth)) {
                    $this->applyWatermark($image);
                }

                $image->save($disk->path($path));

                $variants[$format][(string) $targetWidth] = $path;
            }
        }

        return $variants;
    }

    private function shouldWatermark(int $width): bool
    {
        return $this->watermarkEnabled() && $width >= self::WATERMARK_FROM;
    }

    private function watermarkEnabled(): bool
    {
        return (bool) $this->settings->get('watermark.enabled', true);
    }

    private function applyWatermark(Image $image): void
    {
        $path = $this->settings->get('watermark.path');

        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            return;
        }

        $image->watermark(
            Storage::disk('public')->path($path),
            width: (int) $this->settings->get('watermark.width_percent', 100),
            widthUnit: Unit::Percent,
            alpha: (int) $this->settings->get('watermark.opacity', 10),
        );
    }

    /**
     * Reads dimensions from the file header without decoding the pixels, so an
     * oversized upload is rejected before it can exhaust memory.
     *
     * @return array{0: int, 1: int}
     */
    private function assertSafeDimensions(string $path): array
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('That file is not a readable image.');
        }

        [$width, $height] = $info;
        $megapixels = ($width * $height) / 1_000_000;

        if ($megapixels > self::MAX_MEGAPIXELS) {
            throw new RuntimeException(sprintf(
                'Image is %.1f megapixels; the limit is %d. Please resize it before uploading.',
                $megapixels,
                self::MAX_MEGAPIXELS,
            ));
        }

        return [$width, $height];
    }

    /**
     * Averaged from a 1x1 downscale. Used as the background behind a loading
     * image so the page never flashes grey — part of holding CLS at zero.
     */
    private function dominantColor(string $path): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return '#CCCCCC';
        }

        $tiny = imagescale($source, 1, 1, IMG_BICUBIC);
        imagedestroy($source);

        if ($tiny === false) {
            return '#CCCCCC';
        }

        $rgb = imagecolorat($tiny, 0, 0);
        imagedestroy($tiny);

        return sprintf('#%02X%02X%02X', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
