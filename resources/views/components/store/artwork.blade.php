@props([
    'image' => null,
    'title' => '',
    'sizes' => '(max-width: 768px) 100vw, 50vw',
    'eager' => false,
])

{{--
    One artwork image, or a deliberate placeholder.

    A missing image must never render as a broken icon — that reads as a broken
    site. A branded panel reads as a piece not yet photographed. See §12.6.1.

    Width and height are always emitted so layout shift stays at zero, and the
    dominant colour sits behind the image while it loads.
--}}

@if ($image)
    <picture>
        @if (! empty($image->variants['avif']))
            <source type="image/avif" sizes="{{ $sizes }}" srcset="{{ $image->srcset('avif') }}">
        @endif

        @if (! empty($image->variants['webp']))
            <source type="image/webp" sizes="{{ $sizes }}" srcset="{{ $image->srcset('webp') }}">
        @endif

        <img
            src="{{ $image->url() }}"
            alt="{{ $image->alt() ?: $title }}"
            width="{{ $image->width }}"
            height="{{ $image->height }}"
            @class(['w-full', 'h-auto' => ! str_contains((string) $attributes->get('class'), 'object-cover'), $attributes->get('class')])
            style="background-color: {{ $image->dominant_color ?? '#E2DDD2' }}"
            loading="{{ $eager ? 'eager' : 'lazy' }}"
            fetchpriority="{{ $eager ? 'high' : 'auto' }}"
            decoding="{{ $eager ? 'sync' : 'async' }}"
        >
    </picture>
@else
    <div
        {{ $attributes->class([
            '@container flex w-full flex-col items-center justify-center gap-3 overflow-hidden border rule bg-[color-mix(in_srgb,var(--color-rule)_35%,var(--color-paper))] p-4 text-center',
            // Only when the caller has not fixed a frame of its own, or the two
            // ratios fight and the card stops matching its neighbours.
            'aspect-[4/5]' => ! str_contains((string) $attributes->get('class'), 'aspect-'),
        ]) }}
        role="img"
        aria-label="{{ $title ? $title.' — artwork coming soon' : 'Artwork coming soon' }}"
    >
        {{-- Sized against the box, not the viewport: this same placeholder
             stands in for a 320px card and a 56px line in the order summary. --}}
        <svg class="h-6 w-6 shrink-0 text-muted @min-[12rem]:h-8 @min-[12rem]:w-8"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.25" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="1" />
            <path d="m3 16 5-5 4 4 3-3 6 6" />
            <circle cx="9" cy="8.5" r="1.25" />
        </svg>

        <span class="label hidden @min-[9rem]:block">Coming soon</span>
    </div>
@endif
