<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Product;
use App\Support\Facades\Settings;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    Settings::set('seo.allow_indexing', true);
});

it('describes a product without anyone writing the description', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();

    $html = $this->get(route('product', $product->translate('en')->slug))->assertOk()->getContent();

    expect($html)->toContain('<meta name="description"')
        ->toContain('rel="canonical"')
        // A listing can show the price only if the page says it in a shape
        // a crawler reads.
        ->toContain('application/ld+json')
        ->toContain('"@type":"Product"');
});

it('keeps every filtered listing out of the index, pointing at the shop', function (): void {
    $product = Product::factory()->published()->withTranslation('en')->create();
    $style = Attribute::where('key', 'style')->first()->values()->first();
    $product->attributeValues()->sync([$style->id]);

    // Every combination of facets is a URL. Left alone they are thousands of
    // near-identical pages competing with the one that matters.
    $this->get(route('shop', ['style' => $style->value]))
        ->assertOk()
        ->assertSee('content="noindex,nofollow"', escape: false)
        ->assertSee('rel="canonical" href="'.route('shop').'"', escape: false);

    $this->get(route('shop'))->assertOk()->assertSee('content="index,follow"', escape: false);
});

it('never offers the cart or the checkout to a crawler', function (): void {
    // Followed, because an empty checkout redirects to the cart and the
    // redirect itself carries no head to inspect.
    foreach ([route('cart'), route('checkout'), route('orders.find')] as $url) {
        $this->followingRedirects()->get($url)
            ->assertSee('content="noindex,nofollow"', escape: false);
    }
});

it('lists what is published in the sitemap, and nothing else', function (): void {
    $published = Product::factory()->published()->withTranslation('en')->create();
    $draft = Product::factory()->withTranslation('en')->create();

    $xml = $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->getContent();

    expect($xml)->toContain(route('product', $published->translate('en')->slug))
        ->not->toContain(route('product', $draft->translate('en')->slug));
});

it('offers an attribute page only once enough artwork sits behind it', function (): void {
    $style = Attribute::where('key', 'style')->first()->values()->first();

    foreach (range(1, 3) as $i) {
        Product::factory()->published()->withTranslation('en')->create()
            ->attributeValues()->attach($style->id);
    }

    // Three is a thin page to land on; it earns its URL at four.
    expect($this->get('/sitemap.xml')->getContent())
        ->not->toContain(route('shop', ['style' => $style->value]));

    Product::factory()->published()->withTranslation('en')->create()
        ->attributeValues()->attach($style->id);

    expect($this->get('/sitemap.xml')->getContent())
        ->toContain(route('shop', ['style' => $style->value]));
});

it('shuts the door completely while indexing is off', function (): void {
    Settings::set('seo.allow_indexing', false);
    Product::factory()->published()->withTranslation('en')->create();

    // A soft launch is a store that is finished and not yet findable.
    expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /')
        ->and($this->get('/sitemap.xml')->getContent())->not->toContain('/art/')
        ->and($this->get('/')->getContent())->toContain('content="noindex,nofollow"');
});

it('points robots at the sitemap and away from the private pages', function (): void {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain('Sitemap: '.route('sitemap'))
        ->toContain('Disallow: /cart')
        ->toContain('Disallow: /checkout')
        ->toContain('Disallow: /d/');
});
