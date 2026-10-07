/**
 * The storefront's entire JavaScript.
 *
 * The cart badge is painted from a cookie rather than fetched, so every page
 * stays byte-identical for anonymous visitors and fully CDN-cacheable. See
 * §7.1 — this single decision is worth more to perceived speed than any amount
 * of asset optimisation.
 */
function paintCartCount() {
    const match = document.cookie.match(/(?:^|;\s*)cart_count=(\d+)/);
    const count = match ? parseInt(match[1], 10) : 0;

    document.querySelectorAll('[data-cart-count]').forEach((el) => {
        el.textContent = count > 0 ? String(count) : '';
        el.hidden = count === 0;
    });
}

document.addEventListener('DOMContentLoaded', paintCartCount);
document.addEventListener('cart:changed', paintCartCount);
