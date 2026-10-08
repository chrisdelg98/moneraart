@php
    $all = $products->map(fn ($p) => [
        'product' => $p,
        't' => $p->translate(),
        // Data attributes drive the filter, so it runs with no request at all.
        'style' => $p->valuesFor('style')->pluck('value')->implode(' '),
        'room' => $p->valuesFor('room')->pluck('value')->implode(' '),
        'theme' => $p->valuesFor('theme')->pluck('value')->implode(' '),
    ]);
@endphp

<x-layouts.storefront title="Shop">
    <section class="band border-b rule">
        <div class="mx-auto max-w-[1400px] px-4 py-12 sm:px-8 lg:py-14">
            <h1 class="text-4xl sm:text-5xl">Shop</h1>
            <span class="mt-4 block h-0.5 w-14 bg-accent"></span>
            <p class="mt-5 text-muted">Curated pieces, ready to print or keep.</p>
        </div>
    </section>

    <div class="mx-auto max-w-[1400px] px-4 py-10 sm:px-8">

        @if ($products->isEmpty())
            <p class="mt-6 text-muted">No artwork published yet.</p>
        @else
            <div class="grid gap-10 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-12">

                {{-- A sidebar on a wide screen, a drawer on a narrow one. Stacked
                     above the grid it pushes every artwork off the screen. --}}
                <dialog id="filter-panel" class="filter-panel lg:sticky lg:top-8 lg:self-start"
                        aria-label="Filter artwork">

                    {{-- Filters apply as you type or tick. No Apply button: a filter
                         you have to confirm is a filter most people abandon. --}}
                    <form id="filters" role="search" aria-label="Filter artwork" onsubmit="return false">
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
                                   placeholder="coffee bar, retro&hellip;"
                                   class="mt-2 w-full border rule bg-transparent px-3 py-2 text-sm">

                            @foreach ($facets as $key => $values)
                                <fieldset class="mt-7 border-t rule pt-4">
                                    <legend class="label">{{ Str::headline($key) }}</legend>

                                    <div class="mt-3 space-y-1.5">
                                        @foreach ($values as $value)
                                            <label class="flex cursor-pointer items-center gap-2 py-1 text-sm">
                                                <input type="checkbox" data-facet="{{ $key }}"
                                                       value="{{ $value->value }}" class="shrink-0">
                                                <span>{{ $value->label() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach

                            <button type="button" id="clear-filters"
                                    class="mt-7 text-sm text-muted underline underline-offset-4 hover:text-ink">
                                Clear all
                            </button>
                        </div>

                        {{-- The grid sits behind the backdrop while the drawer is
                             open, so the result count comes to the visitor. --}}
                        <div class="sticky bottom-0 border-t rule bg-paper p-4 lg:hidden">
                            <button type="button" id="filter-done" class="btn-accent w-full px-6 py-3">
                                Show {{ $products->count() }} {{ Str::plural('artwork', $products->count()) }}
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
                            {{ $products->count() }} {{ Str::plural('artwork', $products->count()) }}
                        </p>
                    </div>

                    <ul id="grid" class="mt-6 grid gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($all as $row)
                            <li data-card
                                data-title="{{ Str::lower($row['t']->title.' '.$row['t']->subtitle) }}"
                                data-style="{{ $row['style'] }}"
                                data-room="{{ $row['room'] }}"
                                data-theme="{{ $row['theme'] }}">
                                <x-store.product-card :product="$row['product']" />
                            </li>
                        @endforeach
                    </ul>

                    <p id="no-results" hidden class="mt-10 text-muted">
                        Nothing matches that. <button type="button" id="clear-inline"
                            class="underline underline-offset-4">Clear the filters</button>.
                    </p>
                </div>
            </div>
        @endif
    </div>

    <script>
        (() => {
            const form = document.getElementById('filters');
            if (!form) return;

            const cards = [...document.querySelectorAll('[data-card]')];
            const count = document.getElementById('result-count');
            const empty = document.getElementById('no-results');
            const search = document.getElementById('q');

            const panel = document.getElementById('filter-panel');
            const toggle = document.getElementById('filter-toggle');
            const badge = document.getElementById('filter-count');
            const done = document.getElementById('filter-done');
            const sidebar = matchMedia('(min-width: 1024px)');

            // The drawer needs this script to open, so the button only exists
            // once the script is running.
            toggle.hidden = false;

            const label = (n) => n + (n === 1 ? ' artwork' : ' artworks');

            const apply = () => {
                const term = search.value.trim().toLowerCase();
                const picked = {};

                form.querySelectorAll('[data-facet]:checked').forEach((box) => {
                    (picked[box.dataset.facet] ??= []).push(box.value);
                });

                let shown = 0;

                for (const card of cards) {
                    // Within a facet any value matches; across facets all must.
                    const matches = (!term || card.dataset.title.includes(term))
                        && Object.entries(picked).every(([facet, values]) =>
                            values.some((v) => card.dataset[facet].split(' ').includes(v)));

                    card.hidden = !matches;
                    if (matches) shown++;
                }

                count.textContent = label(shown);
                empty.hidden = shown !== 0;
                done.textContent = 'Show ' + label(shown);

                const active = Object.values(picked).reduce((n, v) => n + v.length, 0);
                badge.textContent = active;
                badge.hidden = active === 0;
            };

            const clear = () => {
                form.reset();
                apply();
                search.focus();
            };

            // Filters as you type, with just enough delay to avoid running on
            // every keystroke of a long word.
            let timer;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(apply, 150);
            });

            form.addEventListener('change', apply);
            document.getElementById('clear-filters').addEventListener('click', clear);
            document.getElementById('clear-inline')?.addEventListener('click', clear);

            // The drawer. Escape, the focus trap and the inert background all
            // come from <dialog> itself.
            toggle.addEventListener('click', () => {
                panel.showModal();
                toggle.setAttribute('aria-expanded', 'true');
            });

            panel.addEventListener('close', () => toggle.setAttribute('aria-expanded', 'false'));
            panel.addEventListener('click', (e) => { if (e.target === panel) panel.close(); });
            document.getElementById('filter-close').addEventListener('click', () => panel.close());
            done.addEventListener('click', () => panel.close());

            // Widening the window turns the drawer back into a sidebar, and a
            // modal left open would keep the page inert behind it.
            sidebar.addEventListener('change', (e) => { if (e.matches && panel.open) panel.close(); });
        })();
    </script>
</x-layouts.storefront>
