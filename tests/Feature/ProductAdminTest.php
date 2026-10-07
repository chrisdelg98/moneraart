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
