<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    $this->actingAs(User::factory()->create());
});

it('renders the product list and create pages', function (): void {
    $this->get('/admin/products')->assertOk();
    $this->get('/admin/products/create')->assertOk();
});

it('creates a product and writes its text to the translation table', function (): void {
    $style = Attribute::where('key', 'style')->first()->values()->first();
    $rooms = Attribute::where('key', 'room')->first()->values()->take(2)->pluck('id')->all();

    livewire(CreateProduct::class)
        ->fillForm([
            'title' => 'Mid-Century Modern Coffee Bar Wall Art',
            'subtitle' => 'Warm retro tones for a kitchen nook',
            'description' => 'Six print-ready pieces for a coffee corner.',
            'price_cents' => '5.99',
            'type' => 'single',
            'status' => ProductStatus::Draft->value,
            'attr_style' => $style->id,
            'attr_room' => $rooms,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    // Price is stored as integer cents, never a float.
    expect($product->price_cents)->toBe(599)
        ->and($product->effectivePrice()->toDecimalString())->toBe('5.99');

    $translation = $product->translate('en');

    expect($translation)->not->toBeNull()
        ->and($translation->title)->toBe('Mid-Century Modern Coffee Bar Wall Art')
        ->and($translation->subtitle)->toBe('Warm retro tones for a kitchen nook')
        // The slug is generated, never typed.
        ->and($translation->slug)->toBe('mid-century-modern-coffee-bar-wall-art')
        // A human wrote this, so automation must leave it alone.
        ->and($translation->is_machine_translated)->toBeFalse()
        ->and($translation->reviewed_at)->not->toBeNull();

    // One style plus two rooms.
    expect($product->attributeValues)->toHaveCount(3)
        ->and($product->valuesFor('room'))->toHaveCount(2)
        ->and($product->valuesFor('style'))->toHaveCount(1);
});

it('disambiguates a slug that collides within the same locale', function (): void {
    foreach (['First', 'Second'] as $i => $_) {
        livewire(CreateProduct::class)
            ->fillForm([
                'title' => 'Coffee Bar',
                'price_cents' => '5.99',
                'type' => 'single',
                'status' => ProductStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    expect(Product::count())->toBe(2)
        ->and(Product::all()->map(fn (Product $p): string => $p->translate('en')->slug)->sort()->values()->all())
        ->toBe(['coffee-bar', 'coffee-bar-2']);
});

it('loads existing text back into the edit form', function (): void {
    $product = Product::factory()->withTranslation('en')->create(['price_cents' => 1299]);
    $title = $product->translate('en')->title;

    livewire(EditProduct::class, ['record' => $product->getKey()])
        ->assertFormSet([
            'title' => $title,
            'price_cents' => '12.99',
        ]);
});

it('rejects a product priced below the fee floor', function (): void {
    livewire(CreateProduct::class)
        ->fillForm([
            'title' => 'Too Cheap',
            'price_cents' => '0.50',
            'type' => 'single',
            'status' => ProductStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['price_cents']);
});

it('requires a title', function (): void {
    livewire(CreateProduct::class)
        ->fillForm(['price_cents' => '5.99', 'type' => 'single'])
        ->call('create')
        ->assertHasFormErrors(['title']);
});

it('makes a published product visible without typing a date', function (): void {
    // "Leave empty to publish immediately" has to actually mean that: the
    // storefront scope requires published_at.
    livewire(CreateProduct::class)
        ->fillForm([
            'title' => 'Visible Right Away',
            'description' => 'A description.',
            'price_cents' => '5.99',
            'type' => 'single',
            'status' => ProductStatus::Published->value,
            'published_at' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    expect($product->published_at)->not->toBeNull()
        ->and(Product::visibleIn('en')->count())->toBe(1);
});

it('puts the save buttons in the header, not below the fold', function (): void {
    // The product form is taller than a screen, so buttons at the foot mean
    // scrolling past everything you just filled in to reach them.
    $page = new CreateProduct;

    expect((fn () => $this->getFormActions())->call($page))->toBe([]);

    $header = collect((fn () => $this->getHeaderActions())->call($page))
        ->map(fn ($action): string => $action->getName());

    // Two answers instead of a dropdown and a generic Create.
    expect($header)->toContain('saveAsDraft')->toContain('publish');
});

/** The header action names for a product in a given state. */
function headerActionsFor(Product $product): Collection
{
    $page = new EditProduct;
    $page->record = $product;

    return collect((fn () => $this->getHeaderActions())->call($page))
        ->map(fn ($action): string => $action->getName());
}

it('offers one-press publish while a product is still a draft', function (): void {
    $names = headerActionsFor(Product::factory()->create(['status' => ProductStatus::Draft]));

    // Delete first, the primary last: on a draft that is Publish, because
    // the alternative is change the select, then press save.
    expect($names)->toContain('delete')->toContain('save')
        ->and($names->last())->toBe('publish');
});

it('drops the publish button once the product is published', function (): void {
    $names = headerActionsFor(Product::factory()->create(['status' => ProductStatus::Published]));

    expect($names)->not->toContain('publish')
        ->and($names->last())->toBe('save');
});

it('lands on the edit page after creating, where the artwork is', function (): void {
    $style = Attribute::where('key', 'style')->first()->values()->first();

    livewire(CreateProduct::class)
        ->fillForm([
            'title' => 'Sunset Geometry',
            'price_cents' => '7.99',
            'type' => 'single',
            'attr_style' => $style->id,
        ])
        ->callAction('saveAsDraft')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    // Relation managers need a saved record, so there is nothing to upload
    // to on the create page. Landing on the index would leave a product with
    // no image and no hint of where to add one.
    expect($product->status)->toBe(ProductStatus::Draft);

    $this->get(EditProduct::getUrl(['record' => $product]))
        ->assertOk()
        ->assertSee('Artwork')
        ->assertSee('Files');
});

it('publishes straight from the create form', function (): void {
    $style = Attribute::where('key', 'style')->first()->values()->first();

    livewire(CreateProduct::class)
        ->fillForm([
            'title' => 'Botanical Silhouette',
            'price_cents' => '5.99',
            'type' => 'single',
            'attr_style' => $style->id,
        ])
        ->callAction('publish')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    // The visibleIn scope needs published_at, so a null one would hide the
    // product forever however green its badge looked.
    expect($product->status)->toBe(ProductStatus::Published)
        ->and($product->published_at)->not->toBeNull();
});

it('says where the artwork is while creating, and not after', function (): void {
    // Without a word here, a create page with no upload box reads as a
    // missing feature rather than a next step.
    $this->get(CreateProduct::getUrl())->assertOk()->assertSee('Artwork and files');

    $this->get(EditProduct::getUrl(['record' => Product::factory()->create()]))
        ->assertOk()
        ->assertDontSee('Saving this takes you straight to');
});
