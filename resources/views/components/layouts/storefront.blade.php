<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>

    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset

    {{-- Kept off search engines until the owner turns indexing on (§11.5). --}}
    @unless(\App\Support\Facades\Settings::get('seo.allow_indexing'))
        <meta name="robots" content="noindex, nofollow">
    @endunless

    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen antialiased">
    {{-- First in the tab order, by requirement (§12.4.1). --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">
        Skip to content
    </a>

    <header class="border-b rule">
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
                <a href="{{ route('shop') }}" class="hover:text-accent" aria-label="Search artwork">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                    </svg>
                </a>

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

                    <p class="label mt-2">Printable wall art</p>

                    {{-- A short accent rule: the one place the brand colour
                         appears in the footer, and it anchors the wordmark. --}}
                    <span class="mt-4 block h-0.5 w-14 bg-accent"></span>

                    <p class="mt-6 max-w-sm font-display text-lg leading-relaxed">
                        Art that brings beauty to your space.<br>
                        Made with AI, selected and prepared by us.
                    </p>
                </div>

                {{-- Aligned with the wordmark's second line rather than its
                     top, so the two blocks read as one band. --}}
                <nav aria-label="Footer" class="flex flex-wrap items-center gap-x-4 gap-y-3 text-sm sm:pt-14">
                    @foreach ([
                        [route('shop'), 'Shop'],
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
