<x-layouts.storefront>
    {{-- Its own band, set apart by a wash rather than a border. --}}
    <section class="band border-b rule">
        <div class="mx-auto flex max-w-[1400px] flex-wrap items-baseline justify-between gap-x-10 gap-y-3 px-4 py-12 sm:px-8 lg:py-16">
            <h1 class="text-balance text-4xl sm:text-5xl">Printable art, yours in seconds.</h1>
            <p class="text-muted">Chosen and prepared for print. No account, no waiting.</p>
        </div>
    </section>

    @if ($featured->isNotEmpty())
        <section class="mx-auto mt-12 max-w-[1400px] px-4 sm:px-8" aria-labelledby="featured">
            <h2 id="featured" class="sr-only">Featured artwork</h2>

            {{-- A pair rather than one piece filling the viewport, with the
                 information sitting on the artwork instead of beside it. --}}
            <ul class="grid gap-6 sm:grid-cols-2">
                @foreach ($featured as $product)
                    @php $t = $product->translate(); @endphp
                    <li>
                        <a href="{{ route('product', $t->slug) }}"
                           class="group relative block overflow-hidden">
                            <x-store.artwork :image="$product->coverImage" :title="$t->title"
                                             sizes="(max-width: 640px) 100vw, 46vw"
                                             class="aspect-[5/4] w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                                             eager />

                            {{-- Text over artwork needs its own floor, whatever
                                 the piece underneath is doing (§12.4.4). --}}
                            <div class="overlay-scrim pointer-events-none absolute inset-x-0 bottom-0 p-6 sm:p-7">
                                <p class="label text-paper/70">{{ $loop->first ? 'Featured' : 'Also new' }}</p>

                                <h3 class="mt-2 text-balance font-display text-2xl leading-tight text-paper sm:text-3xl">
                                    {{ $t->title }}
                                </h3>

                                <p class="mt-3 flex items-baseline gap-3">
                                    <span class="font-display text-2xl text-paper">
                                        {{ $product->effectivePrice()->format() }}
                                    </span>

                                    @if ($product->isOnSale())
                                        <span class="text-sm text-paper/60 line-through">
                                            {{ $product->price()->format() }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- One line, three facts, each with its own mark. --}}
    <section class="mx-auto mt-12 max-w-[1400px] px-4 sm:px-8" aria-label="What to expect">
        <ul class="grid gap-6 border-y rule py-7 sm:grid-cols-3 sm:gap-10">
            <li class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-accent" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4" />
                    <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                </svg>
                <span class="text-sm">
                    <span class="label block">Delivery</span>
                    <span class="mt-0.5 block text-muted">Instant download, straight to your inbox</span>
                </span>
            </li>

            <li class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-accent" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="6" width="18" height="12" rx="1.5" />
                    <path d="m3.5 7 8.5 6 8.5-6" />
                </svg>
                <span class="text-sm">
                    <span class="label block">Account</span>
                    <span class="mt-0.5 block text-muted">Not needed — an email address is all we ask</span>
                </span>
            </li>

            <li class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-accent" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3.5 13.8 9l5.7.2-4.5 3.5 1.6 5.5-4.6-3.2-4.6 3.2 1.6-5.5L4.5 9.2 10.2 9z" />
                </svg>
                <span class="text-sm">
                    <span class="label block">Medium</span>
                    <span class="mt-0.5 block text-muted">
                        Made with AI, curated by us ·
                        <a href="{{ route('legal', 'how-we-work') }}"
                           class="underline underline-offset-4 hover:text-ink">How we work</a>
                    </span>
                </span>
            </li>
        </ul>
    </section>

    @if ($products->isNotEmpty())
        <section class="mx-auto mt-14 max-w-[1400px] px-4 sm:px-8" aria-labelledby="recent">
            <div class="flex items-baseline justify-between gap-6">
                <h2 id="recent" class="text-2xl">New arrivals</h2>
                <a href="{{ route('shop') }}" class="label hover:text-ink">See all →</a>
            </div>

            <ul class="mt-8 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $i => $product)
                    @php $t = $product->translate(); @endphp
                    <li>
                        <a href="{{ route('product', $t->slug) }}" class="group block">
                            <div class="overflow-hidden">
                                <x-store.artwork :image="$product->coverImage" :title="$t->title"
                                                 sizes="(max-width: 640px) 100vw, 420px"
                                                 class="aspect-[4/5] w-full object-cover transition-opacity group-hover:opacity-90" />
                            </div>
                            <x-store.wall-label :product="$product" :index="$i + 3" heading="h3"
                                                compact class="mt-4" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.storefront>
