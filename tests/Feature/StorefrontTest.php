<?php

declare(strict_types=1);

use App\Actions\Products\StoreProductFile;
use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\Product;
use App\Services\Media\ImagePipeline;
use App\Support\Facades\Settings;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('private');
    $this->seed(AttributeSeeder::class);
});

it('renders the home page with nothing published', function (): void {
    // Asserted on structure, not on marketing copy — the headline is meant to
    // change and a test that pins it just breaks every time it does.
    $this->get('/')->assertOk()
        ->assertSee(route('shop'), escape: false)
        ->assertSee('How it works');
});

it('renders the shop and product pages', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $slug = $product->translate('en')->slug;

    $this->get('/shop')->assertOk()->assertSee($product->title('en'));
    $this->get("/art/{$slug}")->assertOk()->assertSee($product->title('en'));
});

it('shows a placeholder instead of a broken image when there is no artwork', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();

    // A broken image icon reads as a broken site. See §12.6.1.
    $this->get('/art/'.$product->translate('en')->slug)
        ->assertOk()
        ->assertSee('Coming soon')
        ->assertSee('artwork coming soon', escape: false);
});

it('offers no buy button when the product has no files to deliver', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();

    $this->get('/art/'.$product->translate('en')->slug)
        ->assertOk()
        ->assertSee('Not available at the moment')
        ->assertDontSee('Add to cart');
});

it('offers the buy button once a sellable file exists', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    app(StoreProductFile::class)($product, artwork(2000, 3000), 'art.jpg');

    $this->get('/art/'.$product->translate('en')->slug)
        ->assertOk()
        ->assertSee('Add to cart')
        ->assertDontSee('Not available at the moment');
});

it('serves responsive sources once artwork is uploaded', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    app(ImagePipeline::class)->process($product, artwork(1600, 2000), isCover: true);

    $this->get('/art/'.$product->translate('en')->slug)
        ->assertOk()
        ->assertSee('image/avif', escape: false)
        ->assertSee('image/webp', escape: false)
        // Dimensions are always emitted so layout shift stays at zero.
        ->assertSee('width="1600"', escape: false);
});

it('hides a draft product from the storefront', function (): void {
    $product = Product::factory()->withTranslation('en')->create(['status' => ProductStatus::Draft]);

    $this->get('/shop')->assertOk()->assertDontSee($product->title('en'));
    $this->get('/art/'.$product->translate('en')->slug)->assertNotFound();
});

it('returns 410 for an archived product rather than 404', function (): void {
    // Gone on purpose de-indexes faster than not found. See §11.3.
    $product = Product::factory()->published()->withTranslation('en')->create();
    $slug = $product->translate('en')->slug;
    $product->update(['status' => ProductStatus::Archived]);

    $this->get("/art/{$slug}")->assertStatus(410);
});

it('404s an unknown slug', function (): void {
    $this->get('/art/nothing-here')->assertNotFound();
});

it('keeps the storefront out of search engines until indexing is turned on', function (): void {
    $this->get('/')->assertOk()->assertSee('noindex', escape: false);

    Settings::set('seo.allow_indexing', true);

    $this->get('/')->assertOk()->assertDontSee('noindex', escape: false);
});

it('exposes the skip link and a single h1 on the product page', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $html = $this->get('/art/'.$product->translate('en')->slug)->assertOk()->getContent();

    expect($html)->toContain('Skip to content')
        ->and(substr_count($html, '<h1'))->toBe(1);
});

it('offers filters only for values a product actually uses', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $style = Attribute::where('key', 'style')->first()->values()->first();
    $product->attributeValues()->sync([$style->id]);

    // A filter for a style nothing has is a dead end the visitor has to
    // discover by clicking it.
    $this->get('/shop')->assertOk()
        ->assertSee($style->label('en'))
        ->assertSee('data-facet', escape: false)
        ->assertDontSee('Art Deco');
});

it('states the real resolution of a product, never a house figure', function (): void {
    // The terms point at the product page for resolution, so this has to be
    // what the customer actually gets.
    $product = Product::factory()->published()->withTranslation('en')->create();
    app(StoreProductFile::class)($product, artwork(1200, 1600), 'art.jpg');

    $html = $this->get('/art/'.$product->translate('en')->slug)->assertOk()->getContent();

    // This file carries no density in its header, so the page states the
    // format rather than inventing a resolution for it.
    expect($html)->toContain('JPG')
        ->and($html)->not->toContain('300 DPI');
});

it('never promises a blanket resolution in the terms', function (): void {
    $this->seed(LegalDocumentSeeder::class);

    // Files vary, so a universal claim is a misrepresentation waiting to
    // happen. The product page is the source of truth.
    $this->get('/terms')->assertOk()
        ->assertDontSee('300 DPI')
        ->assertSee('Every product page lists the resolution');
});

it('arrives at the shop with a style already ticked', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $style = Attribute::where('key', 'style')->first()->values()->first();
    $product->attributeValues()->sync([$style->id]);

    // The home page's tiles link here. Landing on the whole catalogue would
    // make the visitor find the filter again.
    $this->get(route('shop', ['style' => $style->value]))
        ->assertOk()
        ->assertViewHas('selected', ['style' => [$style->value]]);
});

it('ignores a style in the query string that is not a real facet', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $style = Attribute::where('key', 'style')->first()->values()->first();
    $product->attributeValues()->sync([$style->id]);

    // Anything not matching a real facet value is dropped, so the query
    // string can never tick a box that does not exist.
    $this->get(route('shop', ['style' => 'not-a-style']))
        ->assertOk()
        ->assertViewHas('selected', []);
});

it('offers only styles that have artwork to show', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $styles = Attribute::where('key', 'style')->first()->values()->with('translations')->get();
    $product->attributeValues()->sync([$styles->first()->id]);

    // An empty shelf is worse than a shorter row of them.
    $this->get('/')->assertOk()
        ->assertSee($styles->first()->label('en'))
        ->assertDontSee($styles->last()->label('en'));
});
