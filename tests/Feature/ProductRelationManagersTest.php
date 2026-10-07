<?php

declare(strict_types=1);

use App\Actions\Products\StoreProductFile;
use App\Actions\Products\SyncComputedAttributes;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\ImagePipeline;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('private');
    $this->seed(AttributeSeeder::class);
    $this->actingAs(User::factory()->create());
    $this->product = Product::factory()->withTranslation('en')->create();
});

it('renders both managers on the product edit page', function (): void {
    livewire(ImagesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->assertSuccessful();

    livewire(FilesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->assertSuccessful();
});

it('lists artwork with its generated variants', function (): void {
    $image = app(ImagePipeline::class)->process($this->product, artwork(1400, 1800));

    livewire(ImagesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->assertCanSeeTableRecords([$image]);

    expect($image->variants['webp'])->not->toBeEmpty();
});

it('promotes the next image when the cover is deleted', function (): void {
    $pipeline = app(ImagePipeline::class);
    $first = $pipeline->process($this->product, artwork(900, 1200), isCover: true);
    $second = $pipeline->process($this->product, artwork(900, 1200));

    expect($this->product->fresh()->cover_image_id)->toBe($first->id);

    livewire(ImagesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->callTableAction('delete', $first);

    // A dangling cover reference would break every listing card.
    expect($this->product->fresh()->cover_image_id)->toBe($second->id);
});

it('makes another image the cover', function (): void {
    $pipeline = app(ImagePipeline::class);
    $first = $pipeline->process($this->product, artwork(900, 1200), isCover: true);
    $second = $pipeline->process($this->product, artwork(900, 1200));

    livewire(ImagesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->callTableAction('makeCover', $second);

    expect($this->product->fresh()->cover_image_id)->toBe($second->id)
        ->and($second->fresh()->is_cover)->toBeTrue()
        ->and($first->fresh()->is_cover)->toBeFalse();
});

it('stores alt text per language', function (): void {
    $image = app(ImagePipeline::class)->process($this->product, artwork(800, 1000));

    livewire(ImagesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->callTableAction('edit', $image, data: [
        'alt_en' => 'Coffee bar wall art',
        'alt_es' => 'Arte mural de bar de cafe',
    ]);

    expect($image->fresh()->alt('en'))->toBe('Coffee bar wall art')
        ->and($image->fresh()->alt('es'))->toBe('Arte mural de bar de cafe');
});

it('removes derived files when artwork is deleted', function (): void {
    $image = app(ImagePipeline::class)->process($this->product, artwork(1400, 1400));
    $variant = $image->variants['webp'][640] ?? $image->variants['webp']['640'];

    Storage::disk('public')->assertExists($variant);

    livewire(ImagesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->callTableAction('delete', $image);

    Storage::disk('public')->assertMissing($variant);
    Storage::disk('public')->assertMissing($image->path_original);
});

it('recomputes attributes when a file is removed', function (): void {
    $file = app(StoreProductFile::class)(
        $this->product, artwork(2000, 3000), 'portrait.jpg'
    );
    app(SyncComputedAttributes::class)($this->product);

    expect($this->product->valuesFor('ratio')->pluck('value')->all())->toBe(['2-3']);

    livewire(FilesRelationManager::class, [
        'ownerRecord' => $this->product,
        'pageClass' => EditProduct::class,
    ])->callTableAction('delete', $file);

    $this->product->refresh()->load(['files', 'attributeValues.attribute']);

    expect($this->product->valuesFor('ratio'))->toBeEmpty()
        ->and($this->product->file_count)->toBe(0);
});
