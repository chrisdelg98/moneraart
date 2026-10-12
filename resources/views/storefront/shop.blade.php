<x-layouts.storefront title="Shop">
    <section class="band border-b rule">
        <div class="mx-auto max-w-[1400px] px-4 py-12 sm:px-8 lg:py-14">
            <h1 class="text-4xl sm:text-5xl">Shop</h1>
            <span class="mt-4 block h-0.5 w-14 bg-accent"></span>
            <p class="mt-5 text-muted">Curated pieces, ready to print or keep.</p>
        </div>
    </section>

    <div class="mx-auto max-w-[1400px] px-4 py-10 sm:px-8">
        <div class="grid gap-10 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-12">

            {{-- A plain GET form. The script below submits it for you as you
                 type or tick, but with that switched off this is still a
                 working filter: the server does the narrowing either way. --}}
            <dialog id="filter-panel" class="filter-panel lg:sticky lg:top-8 lg:self-start"
                    aria-label="Filter artwork">

                <form id="filters" method="GET" action="{{ route('shop') }}" role="search"
                      aria-label="Filter artwork">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b rule bg-paper px-5 py-4 lg:hidden">
                        <p class="font-display text-lg">Filters</p>

                        <button type="button" id="filter-close"
                                class="-mr-2 flex h-10 w-10 items-center justify-center text-muted hover:text-ink"
                                aria-label="Close filters">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                                <path d="m6 6 12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-5 lg:p-0">
                        <label for="q" class="label">Search</label>
                        <input type="search" id="q" name="q" autocomplete="off"
                               value="{{ $query }}"
                               placeholder="coffee bar, retro&hellip;"
                               class="mt-2 w-full border rule bg-transparent px-3 py-2 text-sm">

                        @foreach ($facets as $key => $values)
                            <fieldset class="mt-7 border-t rule pt-4">
                                <legend class="label">{{ Str::headline($key) }}</legend>

                                <div class="mt-3 space-y-1.5">
                                    @foreach ($values as $value)
                                        <label class="flex cursor-pointer items-center gap-2 py-1 text-sm">
                                            <input type="checkbox" name="{{ $key }}[]" data-facet="{{ $key }}"
                                                   value="{{ $value->value }}" class="shrink-0"
                                                   @checked(in_array($value->value, $selected[$key] ?? [], true))>
                                            <span>{{ $value->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach

                        {{-- A link, not a button: clearing the filters means
                             going back to the unfiltered shop, and that is a
                             URL rather than a state to reset. --}}
                        <a href="{{ route('shop') }}" id="clear-filters"
                           class="mt-7 inline-block text-sm text-muted underline underline-offset-4 hover:text-ink">
                            Clear all
                        </a>
                    </div>

                    <div class="sticky bottom-0 border-t rule bg-paper p-4 lg:hidden">
                        <button type="submit" id="filter-done" class="btn-accent w-full px-6 py-3">
                            Show {{ $products->total() }} {{ Str::plural('artwork', $products->total()) }}
                        </button>
                    </div>
                </form>
            </dialog>

            <div>
                <div class="flex items-center justify-between gap-4">
                    <button type="button" id="filter-toggle" hidden aria-expanded="false" aria-controls="filter-panel"
                            class="flex items-center gap-2 border rule px-4 py-2 text-sm transition-colors hover:border-ink lg:hidden">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 7h16M7 12h10M10 17h4" />
                        </svg>
                        Filters
                        <span id="filter-count" hidden
                              class="ml-1 min-w-5 rounded-full bg-accent px-1.5 text-xs leading-5 text-paper"></span>
                    </button>

                    <p id="result-count" role="status" aria-live="polite" class="label">
                        {{ $products->total() }} {{ Str::plural('artwork', $products->total()) }}
                    </p>
                </div>

                {{-- Everything the script replaces lives in here, so one swap
                     keeps the grid, the empty state and the pager in step. --}}
                <div id="results" class="mt-6" data-total="{{ $products->total() }}">
                    @include('storefront.partials.results')
                </div>
            </div>
        </div>
    </div>

    <script nonce="{{ Vite::cspNonce() }}">
        (() => {
            const form = document.getElementById('filters');
            if (!form) return;

            const results = document.getElementById('results');
            const count = document.getElementById('result-count');
            const search = document.getElementById('q');
            const badge = document.getElementById('filter-count');
            const done = document.getElementById('filter-done');

            const panel = document.getElementById('filter-panel');
            const toggle = document.getElementById('filter-toggle');
            const sidebar = matchMedia('(min-width: 1024px)');

            toggle.hidden = false;

            const label = (n) => n + (n === 1 ? ' artwork' : ' artworks');

            const badges = () => {
                const active = form.querySelectorAll('[data-facet]:checked').length;
                badge.textContent = active;
                badge.hidden = active === 0;
            };

            // The server already knows how to answer this form. Asking it over
            // fetch and swapping the results keeps the page feeling immediate
            // without the grid having to lie about what is not on screen.
            let inFlight;

            const load = async (url, push = true) => {
                inFlight?.abort();
                inFlight = new AbortController();
                results.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, { signal: inFlight.signal });
                    const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const fresh = parsed.getElementById('results');

                    if (fresh) {
                        results.innerHTML = fresh.innerHTML;

                        const total = Number(fresh.dataset.total ?? 0);
                        results.dataset.total = total;
                        count.textContent = label(total);
                        done.textContent = 'Show ' + label(total);
                    }

                    if (push) history.pushState({}, '', url);
                } catch (e) {
                    // An aborted request is the next keystroke arriving, not a
                    // failure. Anything else navigates for real rather than
                    // leaving stale results on screen.
                    if (e.name !== 'AbortError') location.href = url;
                } finally {
                    results.removeAttribute('aria-busy');
                }
            };

            const submit = () => {
                badges();

                const params = new URLSearchParams(new FormData(form));

                // An empty box is noise in a link someone shares.
                for (const [key, value] of [...params]) {
                    if (value === '') params.delete(key);
                }

                const query = params.toString();
                load(form.action + (query ? '?' + query : ''));
            };

            let timer;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(submit, 250);
            });

            form.addEventListener('change', submit);

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                submit();
                panel.close();
            });

            // The pager is inside the swapped markup, so it is caught here
            // rather than bound to links that get replaced.
            results.addEventListener('click', (e) => {
                const link = e.target.closest('[data-page] a');
                if (!link) return;

                e.preventDefault();
                load(link.href);
                results.scrollIntoView({ block: 'start', behavior: 'smooth' });
            });

            addEventListener('popstate', () => load(location.href, false));

            badges();

            // The drawer. Escape, the focus trap and the inert background all
            // come from <dialog> itself.
            toggle.addEventListener('click', () => {
                panel.showModal();
                toggle.setAttribute('aria-expanded', 'true');
            });

            panel.addEventListener('close', () => toggle.setAttribute('aria-expanded', 'false'));
            panel.addEventListener('click', (e) => { if (e.target === panel) panel.close(); });
            document.getElementById('filter-close').addEventListener('click', () => panel.close());

            sidebar.addEventListener('change', (e) => { if (e.matches && panel.open) panel.close(); });
        })();
    </script>
</x-layouts.storefront>
