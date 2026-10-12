<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    $this->search = app(ProductSearch::class);
});

/** A published, translated product with a given title. */
function titled(string $title): Product
{
    $product = Product::factory()->published()->create();

    $product->translations()->create([
        'locale' => 'en',
        'title' => $title,
        'slug' => str($title)->slug()->toString(),
        'status' => 'published',
    ]);

    return $product->refresh();
}

it('finds a product by a word in its title', function (): void {
    titled('Retro Coffee Bar Print');
    titled('Minimal Botanical Study');

    $found = $this->search->paginate('en', 'retro', []);

    expect($found->total())->toBe(1)
        ->and($found->first()->title('en'))->toBe('Retro Coffee Bar Print');
});

it('never lets a search term reach the query as itself', function (): void {
    titled('Retro Coffee Bar Print');

    // Bound, not interpolated. If it were not, this would be a syntax error
    // at best and a dropped table at worst.
    $hostile = "' OR 1=1 --";

    expect(fn () => $this->search->paginate('en', $hostile, [])->total())
        ->not->toThrow(Exception::class)
        ->and($this->search->paginate('en', $hostile, [])->total())->toBe(0);
});

it('treats a wildcard as a character, not as a pattern', function (): void {
    titled('Retro Coffee Bar Print');

    // Unescaped, a bare % through the LIKE fallback matches everything —
    // a wrong answer rather than a crash, which is harder to notice.
    expect($this->search->paginate('en', '%', [])->total())->toBe(0);
});

it('narrows to artwork carrying every facet chosen', function (): void {
    $style = Attribute::where('key', 'style')->first()->values()->where('value', 'retro')->first();
    $room = Attribute::where('key', 'room')->first()->values()->where('value', 'kitchen')->first();

    $both = titled('Retro Kitchen');
    $both->attributeValues()->attach([$style->id, $room->id]);

    $onlyStyle = titled('Retro Nothing Else');
    $onlyStyle->attributeValues()->attach($style->id);

    // Within a facet any value matches; across facets all must.
    expect($this->search->paginate('en', '', ['style' => ['retro']])->total())->toBe(2)
        ->and($this->search->paginate('en', '', ['style' => ['retro'], 'room' => ['kitchen']])->total())->toBe(1);
});

it('does not cross one axis with another that shares a value', function (): void {
    // attribute_values is unique on (attribute_id, value), not on value, so
    // filtering on the bare value could match a theme as if it were a style.
    $theme = Attribute::where('key', 'theme')->first()->values()->first();

    $product = titled('Themed Only');
    $product->attributeValues()->attach($theme->id);

    expect($this->search->paginate('en', '', ['style' => [$theme->value]])->total())->toBe(0);
});

it('pages the catalogue instead of handing over all of it', function (): void {
    foreach (range(1, ProductSearch::PER_PAGE + 5) as $i) {
        titled("Piece number {$i}");
    }

    $page = $this->search->paginate('en', '', []);

    // The count is of everything, not of what fits on the page: a shopper
    // needs to know there is more, and a filter needs to search all of it.
    expect($page->count())->toBe(ProductSearch::PER_PAGE)
        ->and($page->total())->toBe(ProductSearch::PER_PAGE + 5)
        ->and($page->hasPages())->toBeTrue();
});

it('loads a page of products in a handful of queries, not one per card', function (): void {
    foreach (range(1, 10) as $i) {
        $p = titled("Piece {$i}");
        $p->attributeValues()->attach(Attribute::where('key', 'style')->first()->values()->first()->id);
    }

    DB::enableQueryLog();

    $page = $this->search->paginate('en', '', []);

    // Touch everything a card touches while rendering.
    foreach ($page as $product) {
        $product->title('en');
        $product->coverImage;
        $product->valuesFor('style')->map->label();
    }

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Ten products, five eager loads and a count. Anything near twenty means
    // a relation is being fetched per row.
    expect($queries)->toBeLessThan(12);
});

it('binds the term instead of writing it into the SQL', function (): void {
    titled('Retro Coffee Bar Print');

    $hostile = "'; DROP TABLE products; --";

    DB::enableQueryLog();
    app(ProductSearch::class)->paginate('en', $hostile, []);
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // The property that matters whatever the driver underneath: the term
    // travels as a parameter and never as part of the statement.
    expect($log)->not->toBeEmpty()
        ->and($log->contains(fn (array $q): bool => str_contains($q['query'], 'DROP TABLE')))->toBeFalse()
        ->and($log->contains(fn (array $q): bool => collect($q['bindings'])
            ->contains(fn ($b): bool => str_contains((string) $b, 'DROP TABLE'))))->toBeTrue();
});

it('searches through the full-text index where there is one', function (): void {
    titled('Retro Coffee Bar Print');
    titled('Minimal Botanical Study');

    DB::enableQueryLog();
    $found = app(ProductSearch::class)->paginate('en', 'retro', []);
    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    DB::disableQueryLog();

    expect($found->total())->toBe(1)
        // Not LIKE: the index is the whole reason this is in the database.
        ->and($sql)->toContain('MATCH(title, subtitle, description) AGAINST');
})->skip(
    fn (): bool => DB::connection()->getDriverName() !== 'mysql',
    'SQLite has no full-text index; the search falls back to LIKE there.',
);
