@props(['product', 'index' => null, 'heading' => 'h1'])

{{--
    The gallery placard beside a piece.

    The one distinctive component in the system, and it is pure HTML and CSS. It
    is also the accessible version of the same information — the design choice
    and the a11y requirement are one artifact, not a compromise. See §12.2.3.
--}}

@php
    $translation = $product->translate();
    $price = $product->effectivePrice();
    $ratios = $product->valuesFor('ratio')->map(fn ($v) => $v->label())->implode(', ');
@endphp

<div {{ $attributes->class(['flex flex-col gap-4']) }}>
    @if ($index !== null)
        <div class="flex items-center gap-3">
            <span class="label">{{ str_pad((string) $index, 3, '0', STR_PAD_LEFT) }}</span>
            <span class="h-px flex-1 bg-rule"></span>
        </div>
    @endif

    <div>
        <{{ $heading }} class="text-balance text-3xl sm:text-4xl">
            {{ $translation?->title }}
        </{{ $heading }}>

        @if ($translation?->subtitle)
            <p class="mt-2 text-muted">{{ $translation->subtitle }}</p>
        @endif
    </div>

    <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
        <dt class="label self-center">Medium</dt>
        <dd>Digital print{{ $product->is_ai_generated ? ' · made with AI' : '' }}</dd>

        @if ($product->file_count > 0)
            <dt class="label self-center">Files</dt>
            <dd>
                {{ $product->file_count }} {{ Str::plural('file', $product->file_count) }}
                @if ($ratios)
                    · {{ $ratios }}
                @endif
            </dd>
        @endif

        <dt class="label self-center">Delivery</dt>
        <dd>Instant download</dd>
    </dl>

    <p class="flex items-baseline gap-3 text-xl">
        {{-- Price is full-contrast body colour, never "subtle" (§12.4.4). --}}
        <span>{{ $price->format() }}</span>

        @if ($product->isOnSale())
            <span class="text-sm text-muted line-through">{{ $product->price()->format() }}</span>
            <span class="label text-accent">On sale</span>
        @endif
    </p>
</div>
