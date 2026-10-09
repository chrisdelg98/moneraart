<x-layouts.storefront>
    {{-- The photograph is the section, not a block inside it. It is composed
         with empty wall on the left precisely so the copy can live there. --}}
    <section class="relative isolate overflow-hidden">
        {{-- Decorative: the copy above it says everything this photograph
             says, so it carries an empty alt rather than describing furniture. --}}
        <img src="/images/hero-1200.webp"
             srcset="/images/hero-800.webp 800w,
                     /images/hero-1200.webp 1200w,
                     /images/hero-1672.webp 1672w"
             sizes="100vw"
             alt="" width="1672" height="941"
             fetchpriority="high" decoding="sync"
             class="photo-section-image">

        {{-- A wash from the left keeps the copy legible whatever the photograph
             is doing behind it, and fades out before reaching the artwork. --}}
        <div class="photo-section-wash"></div>

        <div class="mx-auto max-w-[1400px] px-4 py-20 sm:px-8 lg:py-32">
            <div class="max-w-md lg:max-w-lg">
                <p class="label">Digital art, ready to use</p>

                <h1 class="mt-5 text-balance text-5xl leading-[1.05] sm:text-6xl">
                    Art that brings beauty<br>
                    <span class="text-accent">to your space.</span>
                </h1>

                <p class="mt-6 text-lg text-muted">
                    Curated pieces, ready to print or keep. Instant download,
                    no account, no waiting.
                </p>

                <a href="{{ route('shop') }}"
                   class="btn-accent mt-8 inline-flex items-center gap-3 px-7 py-3.5">
                    Shop collection
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </section>

    @if ($products->isNotEmpty())
        <section class="mx-auto max-w-[1400px] px-4 py-16 sm:px-8" aria-labelledby="arrivals">
            <div class="flex items-end justify-between gap-6">
                <div>
                    <p class="label">Explore</p>
                    <h2 id="arrivals" class="mt-2 text-4xl">New arrivals</h2>
                </div>

                <a href="{{ route('shop') }}"
                   class="flex shrink-0 items-center gap-2 text-sm text-accent hover:underline hover:underline-offset-4">
                    See all <span aria-hidden="true">→</span>
                </a>
            </div>

            <ul class="mt-10 grid grid-cols-2 gap-4 max-[359px]:grid-cols-1 sm:gap-5 lg:grid-cols-4">
                @foreach ($products as $product)
                    <li>
                        <x-store.product-card :product="$product" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($styles->isNotEmpty())
        {{-- A sand band so the section reads as its own without a heading rule. --}}
        <section class="band-sand" aria-labelledby="collections">
            <div class="mx-auto grid max-w-[1400px] items-center gap-10 px-4 py-16 sm:px-8 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] lg:gap-14">
                <div>
                    <p class="label">Curated collections</p>

                    <h2 id="collections" class="mt-3 text-balance text-4xl leading-tight sm:text-5xl">
                        Find a style that fits.
                    </h2>

                    <p class="mt-5 max-w-xs text-muted">
                        Timeless designs, from modern to classic, for whatever
                        you have in mind.
                    </p>

                    <a href="{{ route('shop') }}"
                       class="btn-accent mt-7 inline-flex items-center gap-3 px-6 py-3">
                        Browse all collections
                        <span aria-hidden="true">→</span>
                    </a>
                </div>

                <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($styles as ['value' => $style, 'cover' => $cover])
                        <li>
                            {{-- The shop reads this and arrives with the filter
                                 already ticked. --}}
                            <a href="{{ route('shop', ['style' => $style->value]) }}"
                               {{-- A fixed height per breakpoint rather than one
                                    ratio: four tiles beside a heading column are
                                    narrow, and a ratio turns narrow into squat. --}}
                               class="group relative block h-44 overflow-hidden sm:h-52 lg:h-60">
                                <x-store.artwork :image="$cover" :title="$style->label()"
                                                 sizes="(max-width: 1024px) 50vw, 260px"
                                                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]" />

                                {{-- Deep top padding is the fade zone: the gradient
                                     needs room above the words to arrive gently. --}}
                                <div class="overlay-scrim pointer-events-none absolute inset-x-0 bottom-0 px-3 pb-3 pt-12">
                                    <p class="text-balance font-display text-base leading-tight text-paper">
                                        {{ $style->label() }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-paper/75">Explore →</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Like the hero: the photograph is the section. Panoramic, with the
         empty wall on the left carrying the copy. --}}
    <section class="relative isolate overflow-hidden" aria-labelledby="how">
        <img src="/images/steps-1280.webp"
             srcset="/images/steps-860.webp 860w,
                     /images/steps-1280.webp 1280w,
                     /images/steps-1920.webp 1920w"
             sizes="100vw"
             alt="" width="1944" height="809" loading="lazy" decoding="async"
             class="photo-section-image">

        <div class="photo-section-wash"></div>

        <div class="mx-auto max-w-[1400px] px-4 py-20 sm:px-8 lg:py-28">
            <div class="max-w-lg lg:max-w-xl">
                <p class="label">How it works</p>
                <h2 id="how" class="mt-3 text-balance text-4xl sm:text-5xl">
                    Get your art in 3 simple steps.
                </h2>

                <ol class="steps relative mt-12 grid gap-10 sm:grid-cols-3 sm:gap-6">
                    @foreach ([
                        ['Choose', 'Browse and buy the piece you want.', 'M6 6h15l-1.5 9h-12z M6 6 5 3H2 M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z M18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z'],
                        ['Download', 'Your print-ready files arrive at once.', 'M12 3v12m0 0 4-4m-4 4-4-4 M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
                        ['Print or keep', 'Print it, frame it, or keep it on screen.', 'M4 5h16v14H4z M4 14l5-5 4 4 3-3 4 4 M9 9.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z'],
                    ] as $i => [$title, $copy, $path])
                        <li class="relative text-center sm:text-left">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-accent-soft)] sm:mx-0">
                                <svg class="h-6 w-6 text-accent" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="{{ $path }}" />
                                </svg>
                            </span>

                            <p class="mt-4 font-medium">{{ $i + 1 }}. {{ $title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $copy }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>
</x-layouts.storefront>
