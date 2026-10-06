<?php

declare(strict_types=1);

use App\Actions\Products\ImportProductsFromJson;
use App\Enums\ProductStatus;
use App\Models\Product;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    $this->import = app(ImportProductsFromJson::class);
});

it('imports the sample payload', function (): void {
    $result = ($this->import)(ImportProductsFromJson::sample());

    expect($result->ok)->toBeTrue()
        ->and($result->created)->toBe(2)
        ->and(Product::count())->toBe(2);
});

it('forces every imported product to draft', function (): void {
    // Artwork cannot arrive by JSON, so a published product would be a broken
    // page. A status in the payload is ignored, not honoured.
    ($this->import)(json_encode([[
        'title' => 'Should Not Publish',
        'price' => '5.99',
        'status' => 'published',
    ]]));

    $product = Product::firstOrFail();

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->published_at)->toBeNull()
        ->and($product->translate('en')->status)->toBe('draft');
});

it('converts decimal prices to integer cents', function (): void {
    ($this->import)(json_encode([
        ['title' => 'A', 'price' => '5.99'],
        ['title' => 'B', 'price' => 12.5],
        ['title' => 'C', 'price' => '1.99'],
    ]));

    expect(Product::pluck('price_cents')->sort()->values()->all())->toBe([199, 599, 1250]);
});

it('creates both language versions when translations are given', function (): void {
    ($this->import)(ImportProductsFromJson::sample());

    $product = Product::whereHas('translations', fn ($q) => $q->where('slug', 'mid-century-modern-coffee-bar-wall-art'))->firstOrFail();

    expect($product->title('en'))->toBe('Mid-Century Modern Coffee Bar Wall Art')
        ->and($product->title('es'))->toBe('Arte mural de bar de café mid-century')
        ->and($product->translate('es')->slug)->toBe('arte-mural-de-bar-de-cafe-mid-century')
        // A human wrote these, so automation must not overwrite them.
        ->and($product->translate('es')->is_machine_translated)->toBeFalse();
});

it('resolves attributes by slug', function (): void {
    ($this->import)(ImportProductsFromJson::sample());

    $product = Product::whereHas('translations', fn ($q) => $q->where('slug', 'mid-century-modern-coffee-bar-wall-art'))->firstOrFail();

    expect($product->valuesFor('style')->pluck('value')->all())->toBe(['mid-century'])
        ->and($product->valuesFor('room')->pluck('value')->sort()->values()->all())->toBe(['coffee-bar', 'kitchen'])
        ->and($product->valuesFor('theme')->pluck('value')->all())->toBe(['coffee']);
});

it('accepts a single object as well as an array', function (): void {
    $result = ($this->import)(json_encode(['title' => 'Just One', 'price' => '5.99']));

    expect($result->ok)->toBeTrue()->and($result->created)->toBe(1);
});

it('rejects malformed json without touching the database', function (): void {
    $result = ($this->import)('{ not json ]');

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0])->toContain('not valid JSON')
        ->and(Product::count())->toBe(0);
});

it('imports nothing when any row is invalid', function (): void {
    // Three good rows and one bad one — a half-populated catalog is worse than
    // a clear failure, so the whole import aborts.
    $result = ($this->import)(json_encode([
        ['title' => 'Good One', 'price' => '5.99'],
        ['title' => 'Good Two', 'price' => '6.99'],
        ['title' => 'Bad', 'price' => '0.50'],
        ['title' => 'Good Three', 'price' => '7.99'],
    ]));

    expect($result->ok)->toBeFalse()
        ->and(Product::count())->toBe(0)
        ->and($result->errors[0])->toContain('below the 1.99 minimum');
});

it('reports a missing title with its position', function (): void {
    $result = ($this->import)(json_encode([['price' => '5.99']]));

    expect($result->errors[0])->toContain('Item 1')->toContain('title');
});

it('reports a missing price by product name', function (): void {
    $result = ($this->import)(json_encode([['title' => 'No Price Here']]));

    expect($result->errors[0])->toContain('No Price Here')->toContain('price');
});

it('rejects an unknown attribute value instead of inventing one', function (): void {
    // Auto-creating taxonomy from a typo pollutes every filter and landing page.
    $result = ($this->import)(json_encode([[
        'title' => 'Typo Test', 'price' => '5.99', 'style' => 'mid-centry',
    ]]));

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0])->toContain('unknown style')
        ->and($result->errors[0])->toContain('Known values include');
});

it('rejects an unknown product type', function (): void {
    $result = ($this->import)(json_encode([[
        'title' => 'Bad Type', 'price' => '5.99', 'type' => 'poster',
    ]]));

    expect($result->errors[0])->toContain('unknown type');
});

it('rejects a language the store does not use', function (): void {
    $result = ($this->import)(json_encode([[
        'title' => 'Wrong Locale', 'price' => '5.99',
        'translations' => ['fr' => ['title' => 'Bonjour']],
    ]]));

    expect($result->errors[0])->toContain('not a language this store uses');
});

it('refuses a product whose name already exists', function (): void {
    ($this->import)(json_encode([['title' => 'Coffee Bar', 'price' => '5.99']]));

    $result = ($this->import)(json_encode([['title' => 'Coffee Bar', 'price' => '6.99']]));

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0])->toContain('already exists')
        ->and(Product::count())->toBe(1);
});

it('allows a free product despite the price floor', function (): void {
    $result = ($this->import)(json_encode([['title' => 'Free Sample', 'price' => '0']]));

    expect($result->ok)->toBeTrue()
        ->and(Product::firstOrFail()->isFree())->toBeTrue();
});

it('summarises what happened', function (): void {
    expect(($this->import)(ImportProductsFromJson::sample())->summary())
        ->toBe('2 products imported as drafts.')
        ->and(($this->import)('nope')->summary())
        ->toContain('Nothing was imported');
});
