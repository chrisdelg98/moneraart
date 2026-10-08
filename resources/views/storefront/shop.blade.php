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

                {{-- Filters apply as you type or tick. No Apply button: a filter
                     you have to confirm is a filter most people abandon. --}}
                <form id="filters" class="lg:sticky lg:top-8 lg:self-start" role="search"
                      aria-label="Filter artwork" onsubmit="return false">
                    <label for="q" class="label">Search</label>
                    <input type="search" id="q" name="q" autocomplete="off"
                           placeholder="coffee bar, retro…"
                           class="mt-2 w-full border rule bg-transparent px-3 py-2 text-sm">

                    @foreach ($facets as $key => $values)
                        <fieldset class="mt-7 border-t rule pt-4">
                            <legend class="label">{{ Str::headline($key) }}</legend>

                            <div class="mt-3 space-y-1.5">
                                @foreach ($values as $value)
                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
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
                </form>

                <div>
                    <p id="result-count" role="status" aria-live="polite" class="label">
                        {{ $products->count() }} {{ Str::plural('artwork', $products->count()) }}
                    </p>

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

                count.textContent = `${shown} ${shown === 1 ? 'artwork' : 'artworks'}`;
                empty.hidden = shown !== 0;
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
        })();
    </script>
</x-layouts.storefront>
