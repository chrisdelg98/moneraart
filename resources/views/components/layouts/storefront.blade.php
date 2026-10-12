@props([
    'title' => null,
    // Declared, because a component has its own scope: a $meta sitting in the
    // page's variables never reaches here, and the head silently fell back to
    // a generated default for every page that thought it had set one.
    'meta' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A page that passes $meta describes itself properly; one that passes
         only a title still gets a generated description and the store's own
         indexing rule, rather than a blank head. --}}
    <x-seo.head :meta="$meta ?? app(\App\Services\Seo\SeoGenerator::class)->forPage(
        $title ?? config('app.name'),
    )" />

    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen antialiased">
    {{-- First in the tab order, by requirement (§12.4.1). --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">
        Skip to content
    </a>

    <header class="relative border-b rule">
        <div class="mx-auto flex max-w-[1400px] items-center justify-between gap-8 px-4 py-4 sm:px-8">
            <a href="{{ route('home') }}" class="font-display text-xl tracking-tight">
                {{ \App\Support\Facades\Settings::get('store.name') }}
            </a>

            <nav aria-label="Main" class="hidden items-center gap-8 text-sm md:flex">
                @foreach ([['home', 'Home'], ['shop', 'Shop']] as [$name, $label])
                    <a href="{{ route($name) }}"
                       @class([
                           'pb-1 hover:text-accent',
                           'border-b-2 border-accent text-accent' => request()->routeIs($name),
                       ])
                       @if (request()->routeIs($name)) aria-current="page" @endif>
                        {{ $label }}
                    </a>
                @endforeach

                <a href="{{ route('legal', 'how-we-work') }}"
                   @class(['pb-1 hover:text-accent', 'border-b-2 border-accent text-accent' => request()->fullUrlIs(route('legal', 'how-we-work'))])>
                    About
                </a>
            </nav>

            <div class="flex items-center gap-5">
                {{--
                    A <details> rather than a scripted panel: the disclosure,
                    the keyboard handling and the open state are the browser's,
                    and the form inside submits with JavaScript off. A magnifier
                    that only navigates to the shop is a promise it does not
                    keep, and teaches people to distrust the rest.
                --}}
                <details class="search-pop" data-search>
                    <summary class="flex cursor-pointer list-none items-center hover:text-accent"
                             aria-label="Search artwork">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                        </svg>
                    </summary>

                    {{-- A band across the full width rather than a card
                         floating over the page: it reads as the header
                         continuing, which is the only way a search bar sitting
                         under a header has ever looked right. --}}
                    <form action="{{ route('shop') }}" method="GET" role="search"
                          class="search-bar absolute inset-x-0 top-full z-40 bg-paper">
                        <div class="mx-auto flex max-w-[1400px] items-center gap-3 px-4 sm:px-8">
                            <svg class="h-5 w-5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                            </svg>

                            <label for="site-search" class="sr-only">Search artwork</label>

                            <input type="search" id="site-search" name="q" autocomplete="off"
                                   placeholder="coffee bar, retro&hellip;"
                                   class="w-full border-0 bg-transparent py-4 text-base">

                            {{-- A filled button, like every other action on
                                 the site. The .label class means caption here,
                                 and nothing happens until this is pressed, so
                                 it has to look pressable. --}}
                            <button type="submit"
                                    class="btn-accent shrink-0 px-5 py-2.5 text-sm">
                                Search
                                <span aria-hidden="true" class="ms-1.5">&rarr;</span>
                            </button>

                            <button type="button" data-search-close
                                    class="-mr-2 flex h-9 w-9 shrink-0 items-center justify-center text-muted hover:text-ink"
                                    aria-label="Close search">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                                    <path d="m6 6 12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </details>

                <a href="{{ route('cart') }}" class="relative hover:text-accent" aria-label="Cart">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6h15l-1.5 9h-12z" /><path d="M6 6 5 3H2" />
                        <circle cx="9" cy="19" r="1" /><circle cx="18" cy="19" r="1" />
                    </svg>
                    <span data-cart-count hidden
                          class="absolute -right-2 -top-2 min-w-[1.1rem] rounded-full bg-accent px-1 text-center text-[0.65rem] leading-[1.1rem] text-paper"></span>
                </a>
            </div>
        </div>
    </header>

    <x-store.toast />

    <main id="main" class="relative">
        {{ $slot }}
    </main>

    <button type="button" id="to-top" class="to-top" hidden aria-label="Back to top">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V6m0 0-6 6m6-6 6 6" />
        </svg>
    </button>

    <footer class="band-footer mt-24 border-t rule">
        <div class="mx-auto max-w-[1400px] px-4 py-16 sm:px-8">
            <div class="flex flex-wrap items-start justify-between gap-x-12 gap-y-10">
                <div>
                    <p class="font-display text-3xl tracking-tight sm:text-4xl">
                        {{ \App\Support\Facades\Settings::get('store.name') }}
                    </p>

                    <p class="label mt-2">Digital art, ready to print</p>

                    {{-- A short accent rule: the one place the brand colour
                         appears in the footer, and it anchors the wordmark. --}}
                    <span class="mt-4 block h-0.5 w-14 bg-accent"></span>

                    <p class="mt-6 max-w-sm font-display text-lg leading-relaxed">
                        Made with AI, selected and prepared by us.<br>
                        Ready to print, or to keep as it is.
                    </p>
                </div>

                {{-- Aligned with the wordmark's second line rather than its
                     top, so the two blocks read as one band. --}}
                <nav aria-label="Footer" class="flex flex-wrap items-center gap-x-4 gap-y-3 text-sm sm:pt-14">
                    @foreach ([
                        [route('shop'), 'Shop'],
                        // Quiet, but present. Someone looking for a lost
                        // purchase looks in the footer before they email you.
                        [route('orders.find'), 'Your orders'],
                        [route('legal', 'how-we-work'), 'About'],
                        [route('legal', 'terms'), 'Terms'],
                        [route('legal', 'privacy'), 'Privacy'],
                        [route('legal', 'refunds'), 'Refunds'],
                    ] as $i => [$url, $label])
                        @if ($i > 0)
                            <span class="text-rule" aria-hidden="true">|</span>
                        @endif

                        <a href="{{ $url }}" class="hover:text-accent">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="mt-14 flex flex-wrap items-center justify-between gap-4 border-t rule pt-6">
                <p class="text-sm text-muted">
                    &copy; {{ date('Y') }} {{ \App\Support\Facades\Settings::get('store.name') }}.
                    All rights reserved.
                </p>

                {{-- Decorative, and marked as such: a rule tapering into three
                     dots closes the page without saying anything. --}}
                <span class="flex items-center gap-2" aria-hidden="true">
                    <span class="h-px w-10 bg-rule"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-accent/50"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-accent/25"></span>
                </span>
            </div>
        </div>
    </footer>
</body>
</html>
