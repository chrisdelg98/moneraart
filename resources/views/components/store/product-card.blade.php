@props(['product'])

@php
    $t = $product->translate();
    $buyable = $product->file_count > 0;
@endphp

{{--
    The catalogue card: artwork, name, price, and one way to buy.

    The whole card is the link, but only the title carries the accessible name —
    a naive whole-card anchor announces the same destination three times. The
    add button sits above it with its own label. See §12.4.2.
--}}
<article {{ $attributes->class(['group relative flex h-full flex-col border rule bg-paper']) }}>
    <div class="relative overflow-hidden">
        <x-store.artwork :image="$product->coverImage" :title="$t->title"
                         sizes="(max-width: 359px) 100vw, (max-width: 767px) 50vw, (max-width: 1279px) 33vw, 320px"
                         class="aspect-[5/4] w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]" />
    </div>

    <div class="flex flex-1 flex-col p-3 sm:p-4">
        <h3 class="text-balance text-sm leading-snug">
            <a href="{{ route('product', $t->slug) }}" class="after:absolute after:inset-0 hover:text-accent">
                {{ $t->title }}
            </a>
        </h3>

        @if ($t->subtitle)
            <p class="mt-1 truncate text-xs text-muted">{{ $t->subtitle }}</p>
        @endif

        {{-- Price and button share the last row so the title keeps the full
             width of the card, which on a phone is barely 160px. --}}
        <div class="mt-auto flex items-end justify-between gap-2 pt-3">
            <p class="flex min-w-0 items-baseline gap-2">
                <span class="font-display text-lg">{{ $product->effectivePrice()->format() }}</span>

                @if ($product->isOnSale())
                    <span class="truncate text-xs text-muted line-through">{{ $product->price()->format() }}</span>
                @endif
            </p>

            @if ($buyable)
                {{-- Above the card link so it stays clickable in its own right. --}}
                <form method="POST" action="{{ route('cart.add', $product->uuid) }}" class="relative z-10 shrink-0">
                    @csrf
                    <button type="submit"
                            class="flex h-10 w-10 items-center justify-center border rule text-accent transition-colors hover:bg-[var(--color-accent-soft)]"
                            aria-label="Add {{ $t->title }} to cart">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 6h15l-1.5 9h-12z" />
                            <path d="M6 6 5 3H2" />
                            <circle cx="9" cy="19" r="1" />
                            <circle cx="18" cy="19" r="1" />
                        </svg>
                    </button>
                </form>
            @else
                <span class="label shrink-0">Soon</span>
            @endif
        </div>
    </div>
</article>
