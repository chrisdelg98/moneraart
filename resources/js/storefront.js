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

        // The number is decorative to a screen reader, which reads the link's
        // label instead, so the count has to go into the label itself.
        const link = el.closest('a');

        if (link) {
            link.setAttribute('aria-label', count === 0
                ? 'Cart, empty'
                : `Cart, ${count} ${count === 1 ? 'item' : 'items'}`);
        }
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

/**
 * The cart toast.
 *
 * The markup is already in the page; this only takes it away again. Hovering
 * or focusing holds it open, because a message that vanishes while you are
 * reading it is worse than no message.
 */
function setUpToast() {
    const toast = document.querySelector('[data-toast]');

    if (!toast) {
        return;
    }

    let timer;

    const dismiss = () => {
        clearTimeout(timer);
        toast.dataset.leaving = '';

        const done = () => { toast.hidden = true; };

        // transitionend never fires when motion is off, so never wait on it.
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? done()
            : toast.addEventListener('transitionend', done, { once: true });
    };

    const hold = () => clearTimeout(timer);
    const resume = () => { timer = setTimeout(dismiss, 4000); };

    toast.querySelector('[data-toast-close]')?.addEventListener('click', dismiss);
    toast.addEventListener('mouseenter', hold);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', hold);
    toast.addEventListener('focusout', resume);

    timer = setTimeout(dismiss, 6000);
}

document.addEventListener('DOMContentLoaded', setUpToast);
