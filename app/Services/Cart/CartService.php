<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The cart holds product ids and nothing else.
 *
 * Prices are resolved from the database on every read, never carried in the
 * session: a price in session state is a price the client's session can be made
 * to disagree with the catalog about. Digital goods have no variants and
 * quantity is always one, so the cart is a short id list.
 *
 * See §7.1 and §16.7.
 */
final class CartService
{
    private const KEY = 'cart';

    /** Deliberately low. A cart of 50 artworks is a mistake, not a purchase. */
    private const MAX_ITEMS = 30;

    public function __construct(private readonly Session $session) {}

    /** @return list<int> */
    public function ids(): array
    {
        /** @var list<int> $ids */
        $ids = $this->session->get(self::KEY, []);

        return $ids;
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function isEmpty(): bool
    {
        return $this->ids() === [];
    }

    public function has(Product $product): bool
    {
        return in_array($product->getKey(), $this->ids(), true);
    }

    public function add(Product $product): CartOutcome
    {
        if (! $product->isPublishedIn(app()->getLocale())) {
            return CartOutcome::rejected('That artwork is not available.');
        }

        // Nothing to deliver means nothing to sell. See §12.6.1.
        if ($product->file_count < 1) {
            return CartOutcome::rejected('That artwork is not available yet.');
        }

        $ids = $this->ids();

        // Buying the same file twice is never the intent.
        if (in_array($product->getKey(), $ids, true)) {
            return CartOutcome::rejected('That artwork is already in your cart.');
        }

        if (count($ids) >= self::MAX_ITEMS) {
            return CartOutcome::rejected('Your cart is full.');
        }

        $ids[] = $product->getKey();
        $this->write($ids);

        return CartOutcome::added();
    }

    public function remove(Product $product): void
    {
        $this->write(array_values(array_filter(
            $this->ids(),
            fn (int $id): bool => $id !== $product->getKey(),
        )));
    }

    public function clear(): void
    {
        $this->write([]);
    }

    /**
     * Cart contents with live prices.
     *
     * Products that have since been unpublished or emptied of files drop out
     * silently rather than reaching checkout as something that cannot be sold.
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        $ids = $this->ids();

        if ($ids === []) {
            return collect();
        }

        $products = Product::query()
            ->whereKey($ids)
            ->with(['translations', 'coverImage'])
            ->get()
            ->filter(fn (Product $p): bool => $p->isPublishedIn(app()->getLocale()) && $p->file_count > 0)
            // Keep the order the customer added them in.
            ->sortBy(fn (Product $p): int => (int) array_search($p->getKey(), $ids, true))
            ->values();

        if ($products->count() !== count($ids)) {
            $this->write($products->map(fn (Product $p): int => $p->getKey())->all());
        }

        return $products;
    }

    public function subtotal(): Money
    {
        return $this->products()->reduce(
            fn (Money $carry, Product $p): Money => $carry->plus($p->effectivePrice()),
            Money::zero((string) config('store.currency', 'USD')),
        );
    }

    /** @param list<int> $ids */
    private function write(array $ids): void
    {
        $this->session->put(self::KEY, array_values(array_unique($ids)));

        // Readable by JavaScript on purpose: the badge is painted from this
        // cookie so the HTML stays identical for every anonymous visitor and
        // the page remains fully CDN-cacheable. See §7.1.
        //
        // httpOnly alone is not enough — bootstrap/app.php exempts the name
        // from cookie encryption as well, or the browser reads ciphertext.
        cookie()->queue(cookie(
            name: 'cart_count',
            value: (string) count($ids),
            minutes: 60 * 24 * 30,
            httpOnly: false,
        ));
    }
}
