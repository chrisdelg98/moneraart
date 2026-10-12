<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Searching and filtering the catalogue, in the database.
 *
 * The shop used to hold every product in the page and hide the ones that did
 * not match. That is why it felt instant, and why it could not be paginated —
 * a page of twenty-four cannot honestly answer "does anything match retro".
 *
 * Deliberately not Meilisearch. At a few hundred pieces MySQL's own full-text
 * index answers in single-digit milliseconds with no second server to run,
 * and the one thing it gives up — typo tolerance — is worth less than the
 * operational weight on a catalogue this size. See §2b.
 */
final class ProductSearch
{
    public const PER_PAGE = 24;

    /**
     * InnoDB does not index words shorter than this, so a two-letter term
     * would silently match nothing. Those fall back to LIKE.
     */
    private const MIN_INDEXED_WORD = 3;

    /**
     * @param  array<string, list<string>>  $facets  attribute key => chosen values
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(string $locale, string $term, array $facets): LengthAwarePaginator
    {
        return Product::visibleIn($locale)
            // Every one of these is read while rendering a card. Without them
            // a page of twenty-four products is a hundred queries.
            ->with(['translations', 'coverImage', 'attributeValues.attribute', 'attributeValues.translations'])
            ->when($term !== '', fn (Builder $q) => $q->whereHas(
                'translations',
                fn (Builder $t) => $t->where('locale', $locale)->where(
                    fn (Builder $match) => $this->matchText($match, $term),
                ),
            ))
            ->tap(fn (Builder $q) => $this->applyFacets($q, $facets))
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Within a facet any value matches; across facets all must.
     *
     * Each clause is tied to its attribute key as well as its value, because
     * attribute_values is unique on (attribute_id, value) and not on value
     * alone — a theme and a style are allowed to share a name, and filtering
     * on the bare value would quietly cross the two.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, list<string>>  $facets
     */
    private function applyFacets(Builder $query, array $facets): void
    {
        foreach ($facets as $key => $values) {
            if ($values === []) {
                continue;
            }

            $query->whereHas('attributeValues', fn (Builder $q) => $q
                ->whereIn('attribute_values.value', $values)
                ->whereHas('attribute', fn (Builder $a) => $a->where('key', $key)));
        }
    }

    /**
     * @param  Builder<ProductTranslation>  $query
     */
    private function matchText(Builder $query, string $term): void
    {
        $longest = collect(preg_split('/\s+/', $term) ?: [])
            ->map(fn (string $word): int => mb_strlen($word))
            ->max() ?? 0;

        // SQLite, which the tests run on, has no MATCH AGAINST at all, and a
        // term of only short words would match nothing through the index.
        if (DB::connection()->getDriverName() !== 'mysql' || $longest < self::MIN_INDEXED_WORD) {
            $this->matchLike($query, $term);

            return;
        }

        // Bound, never interpolated. The expression is a fixed string and the
        // only thing that varies is the parameter.
        $query->whereRaw(
            'MATCH(title, subtitle, description) AGAINST (? IN BOOLEAN MODE)',
            [$this->booleanTerm($term)],
        );
    }

    /** @param Builder<ProductTranslation> $query */
    private function matchLike(Builder $query, string $term): void
    {
        // Escaped as well as bound: without this a term containing % matches
        // the whole catalogue, which is a wrong answer rather than a hole.
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where('title', 'like', $like)
            ->orWhere('subtitle', 'like', $like)
            ->orWhere('description', 'like', $like);
    }

    /**
     * Turns what someone typed into a boolean-mode expression.
     *
     * Binding keeps this safe from injection; stripping the operators keeps it
     * safe from itself, because a stray + or ( makes MySQL reject the whole
     * query. Each word gets a trailing * so "retr" still finds "retro".
     */
    private function booleanTerm(string $term): string
    {
        return collect(preg_split('/\s+/', $term) ?: [])
            ->map(fn (string $word): string => preg_replace('/[+\-><()~*"@]+/', '', $word) ?? '')
            ->filter(fn (string $word): bool => mb_strlen($word) >= self::MIN_INDEXED_WORD)
            ->map(fn (string $word): string => $word.'*')
            ->take(8)
            ->implode(' ');
    }

    /** Trimmed and capped: it goes back into the field it came from. */
    public static function clean(?string $term): string
    {
        return Str::limit(trim((string) $term), 64, '');
    }
}
