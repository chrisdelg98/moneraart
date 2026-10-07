<?php

declare(strict_types=1);

use App\Actions\Products\StoreProductFile;
use App\Actions\Products\SyncComputedAttributes;
use App\Models\Attribute;
use App\Models\Product;
use App\Services\Media\ImagePipeline;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);
    $this->store = app(StoreProductFile::class);
    $this->sync = app(SyncComputedAttributes::class);
    $this->pipeline = app(ImagePipeline::class);
});

it('stores a sellable file on the private disk under a uuid name', function (): void {
    $product = Product::factory()->create();

    $file = ($this->store)($product, artwork(600, 900), 'coffee-bar-16x20.jpg');

    expect($file->disk)->toBe('private')
        // A leaked path must reveal nothing about the catalog.
        ->and($file->path)->not->toContain('coffee-bar')
        ->and($file->original_filename)->toBe('coffee-bar-16x20.jpg')
        ->and($file->display_name)->toBe('coffee-bar-16x20');

    Storage::disk('private')->assertExists($file->path);
});

it('reads dimensions and ratio from the file instead of asking for them', function (): void {
    $product = Product::factory()->create();

    $file = ($this->store)($product, artwork(2000, 3000), 'art.jpg');

    expect($file->width_px)->toBe(2000)
        ->and($file->height_px)->toBe(3000)
        ->and($file->ratio)->toBe('2:3');
});

it('leaves the ratio empty rather than guessing on an odd shape', function (): void {
    $product = Product::factory()->create();

    // A 4:1 panorama matches nothing sensibly.
    expect(($this->store)($product, artwork(4000, 1000), 'pano.jpg')->ratio)->toBeNull();
});

it('records a checksum and refuses the same bytes twice', function (): void {
    $product = Product::factory()->create();
    $path = artwork(800, 800);

    $first = ($this->store)($product, $path, 'first.jpg');

    expect($first->checksum_sha256)->toMatch('/^[0-9a-f]{64}$/');

    ($this->store)($product, $path, 'duplicate.jpg');
})->throws(RuntimeException::class, 'already attached');

it('rejects a file type a customer cannot print', function (): void {
    $product = Product::factory()->create();
    $path = sys_get_temp_dir().'/'.uniqid('x_', true).'.jpg';
    // A .jpg extension on PHP source: the extension is a claim, the bytes are not.
    file_put_contents($path, '<?php echo "payload";');

    ($this->store)($product, $path, 'sneaky.jpg');
})->throws(RuntimeException::class);

it('keeps the denormalised file count and size on the product', function (): void {
    $product = Product::factory()->create();

    ($this->store)($product, artwork(800, 1000), 'one.jpg');
    ($this->store)($product, artwork(900, 1200), 'two.jpg');

    $product->refresh();

    expect($product->file_count)->toBe(2)
        ->and($product->total_bytes)->toBeGreaterThan(0);
});

it('derives ratio and orientation from the uploaded files', function (): void {
    $product = Product::factory()->create();

    ($this->store)($product, artwork(2000, 3000), 'portrait.jpg');   // 2:3
    ($this->store)($product, artwork(3000, 2000), 'landscape.jpg');  // 3:2

    ($this->sync)($product);

    expect($product->valuesFor('ratio')->pluck('value')->sort()->values()->all())->toBe(['2-3', '3-2'])
        ->and($product->valuesFor('orientation')->pluck('value')->sort()->values()->all())
        ->toBe(['landscape', 'portrait']);
});

it('derives a colour from the preview artwork', function (): void {
    $product = Product::factory()->create();

    // A warm amber-brown.
    $this->pipeline->process($product, artwork(800, 1000, [200, 150, 60]));
    ($this->sync)($product);

    expect($product->valuesFor('color')->pluck('value')->all())->toBe(['amber']);
});

it('names a near-grey artwork by lightness, not by hue', function (): void {
    $product = Product::factory()->create();

    $this->pipeline->process($product, artwork(800, 800, [30, 30, 32]));
    ($this->sync)($product);

    expect($product->valuesFor('color')->pluck('value')->all())->toBe(['charcoal']);
});

it('never disturbs the three attributes the administrator chose', function (): void {
    $product = Product::factory()->create();

    $style = Attribute::where('key', 'style')->first()->values()->first();
    $room = Attribute::where('key', 'room')->first()->values()->first();
    $product->attributeValues()->sync([$style->id, $room->id]);

    ($this->store)($product, artwork(2000, 3000), 'art.jpg');
    ($this->sync)($product);

    expect($product->valuesFor('style')->pluck('id')->all())->toBe([$style->id])
        ->and($product->valuesFor('room')->pluck('id')->all())->toBe([$room->id])
        ->and($product->valuesFor('ratio'))->not->toBeEmpty();
});

it('drops a computed value that no longer applies', function (): void {
    $product = Product::factory()->create();

    $file = ($this->store)($product, artwork(2000, 3000), 'portrait.jpg');
    ($this->sync)($product);

    expect($product->valuesFor('orientation')->pluck('value')->all())->toBe(['portrait']);

    $file->delete();
    $product->load('files');
    ($this->sync)($product);

    expect($product->valuesFor('orientation'))->toBeEmpty();
});
