<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Support\Facades\Settings;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * The sitemap and robots.txt, both generated.
 *
 * Nothing to remember to update: a product published this morning is in the
 * sitemap this morning. The alternative is a file that is correct on the day
 * it is written and wrong from the next publish onwards. See §11.5.
 */
class SitemapController
{
    /**
     * An attribute page earns a crawlable URL only once it holds enough
     * artwork to be worth landing on. See §11.5.
     */
    private const MIN_PRODUCTS_FOR_INDEX = 4;

    public function sitemap(): Response
    {
        $locale = app()->getLocale();

        // A store that is not findable has nothing to offer a crawler, and an
        // empty sitemap says that more clearly than a full one it ignores.
        $urls = Settings::get('seo.allow_indexing') ? $this->urls($locale) : collect();

        return response()
            ->view('storefront.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $indexable = (bool) Settings::get('seo.allow_indexing');

        $lines = $indexable
            ? [
                'User-agent: *',
                // Named rather than left to the noindex tags: a crawler that
                // never fetches these never spends the budget on them.
                'Disallow: /cart',
                'Disallow: /checkout',
                'Disallow: /order/',
                'Disallow: /orders',
                'Disallow: /d/',
                'Disallow: /'.trim((string) Settings::get('store.admin_path', 'admin'), '/'),
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string, priority: string}> */
    private function urls(string $locale): Collection
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null, 'priority' => '1.0'],
            ['loc' => route('shop'), 'lastmod' => null, 'priority' => '0.9'],
        ]);

        $products = Product::visibleIn($locale)
            ->with('translations')
            ->get()
            ->map(fn (Product $p): array => [
                'loc' => route('product', $p->translate($locale)?->slug),
                'lastmod' => $p->updated_at?->toAtomString(),
                'priority' => '0.8',
            ]);

        // Filtered listings carry noindex, so only the ones with enough behind
        // them are offered — and they are offered as the shop's own filter.
        $landing = AttributeValue::query()
            ->whereHas('attribute', fn ($q) => $q->where('is_seo_landing', true))
            ->withCount(['products' => fn ($q) => $q->visibleIn($locale)])
            ->with('attribute')
            ->get()
            ->filter(fn (AttributeValue $v): bool => $v->products_count >= self::MIN_PRODUCTS_FOR_INDEX)
            ->map(fn (AttributeValue $v): array => [
                'loc' => route('shop', [$v->attribute->key => $v->value]),
                'lastmod' => null,
                'priority' => '0.6',
            ]);

        $legal = collect(['terms', 'privacy', 'refunds', 'how-we-work'])
            ->map(fn (string $slug): array => [
                'loc' => route('legal', $slug),
                'lastmod' => null,
                'priority' => '0.3',
            ]);

        return $urls->concat($products)->concat($landing)->concat($legal)->values();
    }
}
