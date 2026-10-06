<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the list price when nothing is on sale', function (): void {
    $product = Product::factory()->create(['price_cents' => 599]);

    expect($product->effectivePrice()->toDecimalString())->toBe('5.99')
        ->and($product->isOnSale())->toBeFalse();
});

it('applies a sale price inside its window', function (): void {
    $product = Product::factory()->onSale(399)->create(['price_cents' => 599]);

    expect($product->isOnSale())->toBeTrue()
        ->and($product->effectivePrice()->toDecimalString())->toBe('3.99');
});

it('ignores a sale whose window has passed', function (): void {
    $product = Product::factory()->create([
        'price_cents' => 599,
        'sale_price_cents' => 399,
        'sale_starts_at' => now()->subDays(10),
        'sale_ends_at' => now()->subDay(),
    ]);

    expect($product->isOnSale())->toBeFalse()
        ->and($product->effectivePrice()->toDecimalString())->toBe('5.99');
});

it('ignores a sale whose window has not started', function (): void {
    $product = Product::factory()->create([
        'price_cents' => 599,
        'sale_price_cents' => 399,
        'sale_starts_at' => now()->addDay(),
    ]);

    expect($product->isOnSale())->toBeFalse();
});

it('ignores a sale price that is not actually cheaper', function (): void {
    $product = Product::factory()->onSale(799)->create(['price_cents' => 599]);

    expect($product->isOnSale())->toBeFalse();
});

it('recognises a free product', function (): void {
    expect(Product::factory()->free()->create()->isFree())->toBeTrue()
        ->and(Product::factory()->create(['price_cents' => 199])->isFree())->toBeFalse();
});

it('keeps slugs unique per locale but allows reuse across locales', function (): void {
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    ProductTranslation::create([
        'product_id' => $a->id, 'locale' => 'en', 'title' => 'Coffee Bar', 'slug' => 'coffee-bar',
    ]);

    // Same slug, different locale — allowed.
    ProductTranslation::create([
        'product_id' => $a->id, 'locale' => 'es', 'title' => 'Bar de Café', 'slug' => 'coffee-bar',
    ]);

    expect(ProductTranslation::where('slug', 'coffee-bar')->count())->toBe(2);

    // Same slug, same locale, different product — rejected by the database.
    expect(fn () => ProductTranslation::create([
        'product_id' => $b->id, 'locale' => 'en', 'title' => 'Other', 'slug' => 'coffee-bar',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('hides a product from a locale it has no published translation in', function (): void {
    $product = Product::factory()->published()
        ->withTranslation('en', 'published')
        ->withTranslation('es', 'draft')
        ->create();

    expect($product->isPublishedIn('en'))->toBeTrue()
        ->and($product->isPublishedIn('es'))->toBeFalse();
});

it('scopes the catalog to products visible in a locale', function (): void {
    Product::factory()->published()->withTranslation('en')->withTranslation('es')->create();
    Product::factory()->published()->withTranslation('en')->create();
    Product::factory()->withTranslation('en')->create();   // draft product

    expect(Product::visibleIn('en')->count())->toBe(2)
        ->and(Product::visibleIn('es')->count())->toBe(1);
});

it('excludes unpublished products from the published scope', function (): void {
    Product::factory()->published()->create();
    Product::factory()->create(['status' => ProductStatus::Draft]);
    Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => now()->addWeek()]);

    expect(Product::published()->count())->toBe(1);
});

it('links a bundle to its children without duplicating files', function (): void {
    $bundle = Product::factory()->create(['type' => 'bundle']);
    $children = Product::factory()->count(3)->create();

    $bundle->bundledProducts()->attach($children->pluck('id'));

    expect($bundle->bundledProducts)->toHaveCount(3);
});
