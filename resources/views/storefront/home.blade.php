<x-layouts.storefront>
    @if ($featured)
        @php $t = $featured->translate(); @endphp

        {{-- One full-bleed piece per page, no more. Asymmetric on purpose:
             centred content reads as a default. See §12.2.4. --}}
        <section class="mx-auto grid max-w-[1400px] items-center gap-10 px-4 py-14 sm:px-8 lg:grid-cols-12 lg:gap-16 lg:py-24">
            <div class="lg:col-span-7">
                <a href="{{ route('product', $t->slug) }}">
                    <x-store.artwork :image="$featured->coverImage" :title="$t->title"
                                     sizes="(max-width: 1024px) 100vw, 58vw" eager />
                </a>
            </div>

            <div class="lg:col-span-4 lg:col-start-9">
                <p class="label">Featured</p>
                <h1 class="mt-4 text-balance text-5xl leading-[0.95] sm:text-6xl">{{ $t->title }}</h1>

                @if ($t->subtitle)
                    <p class="mt-5 max-w-prose text-lg text-muted">{{ $t->subtitle }}</p>
                @endif

                <a href="{{ route('product', $t->slug) }}"
                   class="mt-8 inline-block border border-ink px-6 py-3 transition-colors hover:bg-ink hover:text-paper">
                    View this piece
                </a>
            </div>
        </section>
    @else
        <section class="mx-auto max-w-[1400px] px-4 py-24 sm:px-8">
            <h1 class="max-w-3xl text-balance text-5xl leading-[0.95] sm:text-6xl">
                Printable art, delivered the moment you buy it.
            </h1>
            <p class="mt-6 max-w-prose text-lg text-muted">
                Nothing published yet. Add artwork from the admin and it appears here.
            </p>
        </section>
    @endif

    @if ($products->isNotEmpty())
        <section class="mx-auto max-w-[1400px] border-t rule px-4 py-14 sm:px-8" aria-labelledby="recent">
            <h2 id="recent" class="text-2xl">New arrivals</h2>

            <ul class="mt-10 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $i => $product)
                    @php $t = $product->translate(); @endphp
                    {{-- Varied card sizes on a repeating rhythm, not a uniform
                         matrix — this is most of what separates a gallery from
                         a WooCommerce grid, and it is one CSS rule. --}}
                    <li @class(['lg:col-span-2' => $i % 5 === 0])>
                        <a href="{{ route('product', $t->slug) }}" class="group block">
                            <x-store.artwork :image="$product->coverImage" :title="$t->title"
                                             sizes="(max-width: 640px) 100vw, 420px"
                                             class="transition-opacity group-hover:opacity-90" />
                            <x-store.wall-label :product="$product" :index="$i + 1" heading="h3" class="mt-5" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.storefront>
