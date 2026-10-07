<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImagePipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->pipeline = app(ImagePipeline::class);
});

it('generates webp and avif variants at every width below the original', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(2400, 3200));

    expect($image)->toBeInstanceOf(ProductImage::class)
        ->and($image->variants)->toHaveKeys(['webp', 'avif'])
        ->and(array_map('intval', array_keys($image->variants['webp'])))->toBe([320, 640, 960, 1280, 1920])
        ->and(array_map('intval', array_keys($image->variants['avif'])))->toBe([320, 640, 960, 1280, 1920]);

    foreach ($image->variants['webp'] as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('never upscales past the original width', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(700, 900));

    // 960, 1280 and 1920 would all be upscales — bandwidth for no detail.
    expect(array_map('intval', array_keys($image->variants['webp'])))->toBe([320, 640]);
});

it('records dimensions so the markup can prevent layout shift', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(1200, 1600));

    expect($image->width)->toBe(1200)
        ->and($image->height)->toBe(1600)
        ->and($image->orientation())->toBe('portrait');
});

it('extracts a dominant colour for the loading placeholder', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(600, 600));

    expect($image->dominant_color)->toMatch('/^#[0-9A-F]{6}$/');
});

it('rejects an image above the megapixel cap before decoding it', function (): void {
    $product = Product::factory()->create();

    // 9000x6000 = 54MP, over the 50MP limit.
    $this->pipeline->process($product, artwork(9000, 6000));
})->throws(RuntimeException::class, 'megapixels');

it('rejects a file that is not an image', function (): void {
    $product = Product::factory()->create();
    $path = sys_get_temp_dir().'/'.uniqid('fake_', true).'.jpg';
    file_put_contents($path, '<?php echo "not an image";');

    $this->pipeline->process($product, $path);
})->throws(RuntimeException::class, 'not a readable image');

it('re-encodes the original, which strips any embedded metadata', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(800, 800));

    // Stored as webp regardless of the uploaded format — a copied file would
    // keep its original extension and its EXIF.
    expect($image->path_original)->toEndWith('.webp');
    Storage::disk('public')->assertExists($image->path_original);
});

it('sets the product cover when asked', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(800, 1000), isCover: true);

    expect($product->fresh()->cover_image_id)->toBe($image->id)
        ->and($image->is_cover)->toBeTrue();
});

it('builds a srcset from the stored variant map without touching disk', function (): void {
    $product = Product::factory()->create();

    $image = $this->pipeline->process($product, artwork(2000, 2000));

    expect($image->srcset('webp'))->toContain('320w')->toContain('1920w')
        ->and($image->orientation())->toBe('square');
});

it('builds urls correctly after a database round trip', function (): void {
    $product = Product::factory()->create();
    $image = $this->pipeline->process($product, artwork(1400, 1400));

    // JSON turns the integer keys into strings; variantUrls must survive that.
    $reloaded = ProductImage::findOrFail($image->id);

    expect(array_keys($reloaded->variantUrls('webp')))->toBe([320, 640, 960, 1280])
        ->and($reloaded->srcset('webp'))->toContain('1280w');
});
