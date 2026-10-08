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

/**
 * Back to top.
 *
 * An IntersectionObserver on a sentinel at the head of the page rather than a
 * scroll listener: the browser does the watching, so nothing runs on every
 * frame of a scroll.
 */
function setUpBackToTop() {
    const button = document.getElementById('to-top');
    const main = document.getElementById('main');

    if (!button || !main) {
        return;
    }

    const sentinel = document.createElement('div');
    sentinel.style.cssText = 'position:absolute;top:0;height:60vh;width:1px;pointer-events:none;';
    main.prepend(sentinel);

    new IntersectionObserver(
        ([entry]) => { button.hidden = entry.isIntersecting; },
        { threshold: 0 },
    ).observe(sentinel);

    button.addEventListener('click', () => {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });

        // Scrolling alone leaves focus stranded at the foot of the page, so a
        // keyboard user would carry on tabbing from wherever they were.
        document.querySelector('header a')?.focus({ preventScroll: true });
    });
}

document.addEventListener('DOMContentLoaded', setUpBackToTop);
