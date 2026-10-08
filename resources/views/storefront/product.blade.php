@php
    $cover = $product->coverImage ?? $product->images->first();
    $gallery = $product->images->where('id', '!=', $cover?->id);
    $buyable = $product->file_count > 0;
@endphp

<x-layouts.storefront :title="$translation->title" :description="Str::limit(strip_tags((string) $translation->description), 155)">

    <nav aria-label="Breadcrumb" class="mx-auto max-w-[1400px] px-4 pt-6 text-sm sm:px-8">
        <ol class="flex items-center gap-2 text-muted">
            <li><a href="{{ route('home') }}" class="hover:text-ink">Home</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('shop') }}" class="hover:text-ink">Shop</a></li>
            <li aria-hidden="true">/</li>
            <li><span aria-current="page" class="text-ink">{{ $translation->title }}</span></li>
        </ol>
    </nav>

    <div class="mx-auto grid max-w-[1400px] gap-10 px-4 py-10 sm:px-8 lg:grid-cols-[auto_minmax(0,1fr)] lg:gap-16">

        {{-- The artwork is the argument; it takes most of the first viewport. --}}
        <div class="flex flex-col gap-4">
            <div class="w-full max-w-[460px] overflow-hidden">
                <x-store.artwork
                    :image="$cover"
                    :title="$translation->title"
                    sizes="(max-width: 1024px) 100vw, 460px"
                    class="aspect-[4/5] w-full object-cover"
                    eager
                />
            </div>

            @if ($gallery->isNotEmpty())
                <ul class="grid w-full max-w-[460px] grid-cols-4 gap-3">
                    @foreach ($gallery as $image)
                        <li>
                            <x-store.artwork
                                :image="$image"
                                :title="$translation->title"
                                sizes="110px"
                                class="aspect-square w-full object-cover"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex flex-col gap-6 lg:sticky lg:top-8 lg:self-start">
            <x-store.wall-label :product="$product" heading="h1" />

            @if ($buyable && $inCart)
                {{-- Already held. Offering "add to cart" again only leads to a
                     refusal, so the page offers the two things that are left:
                     go and pay, or take it back out. --}}
                <div class="flex flex-col gap-3">
                    <p class="flex items-center gap-2.5 border rule bg-accent-soft px-5 py-4 text-sm">
                        <svg class="h-5 w-5 shrink-0 text-accent" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" /><path d="m8.5 12.5 2.5 2.5 4.5-5" />
                        </svg>
                        In your cart
                    </p>

                    <a href="{{ route('cart') }}" class="btn-accent px-6 py-3.5 text-center">
                        View cart &mdash; {{ $product->effectivePrice()->format() }}
                    </a>

                    <form method="POST" action="{{ route('cart.remove', $product->uuid) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full py-1 text-sm text-muted underline underline-offset-4 hover:text-accent">
                            Remove from cart
                        </button>
                    </form>
                </div>
            @elseif ($buyable)
                <form method="POST" action="{{ route('cart.add', $product->uuid) }}" class="flex flex-col gap-3">
                    @csrf

                    <button type="submit"
                            class="w-full border border-ink bg-ink px-6 py-3.5 text-paper transition-opacity hover:opacity-85">
                        Add to cart &mdash; {{ $product->effectivePrice()->format() }}
                    </button>

                    <p class="label text-center">Instant download · No account needed</p>
                </form>
            @else
                {{-- No files means nothing to deliver. Quiet, not alarming, and
                     never a button that cannot do anything. See §12.6.1. --}}
                <div class="border rule bg-[color-mix(in_srgb,var(--color-rule)_30%,var(--color-paper))] px-6 py-5">
                    <p class="font-display text-lg">Not available at the moment</p>
                    <p class="mt-1 text-sm text-muted">
                        This piece is being prepared. Check back shortly.
                    </p>
                </div>
            @endif

            @if ($translation->description)
                <div class="max-w-prose border-t rule pt-6 leading-relaxed">
                    {{ $translation->description }}
                </div>
            @endif

            @if ($product->is_ai_generated)
                <p class="label">
                    ✦ Made with AI, curated and prepared by us
                </p>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-[1400px] border-t rule px-4 pt-12 sm:px-8" aria-labelledby="related">
            <h2 id="related" class="text-2xl">You may also like</h2>

            <ul class="mt-8 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($related as $i => $item)
                    @php $t = $item->translate(); @endphp
                    <li>
                        <a href="{{ route('product', $t->slug) }}" class="group block">
                            <x-store.artwork
                                :image="$item->coverImage"
                                :title="$t->title"
                                sizes="(max-width: 640px) 100vw, 320px"
                                class="transition-opacity group-hover:opacity-90"
                            />
                            <p class="mt-3 text-balance">{{ $t->title }}</p>
                            <p class="text-sm text-muted">{{ $item->effectivePrice()->format() }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.storefront>
