@props(['product', 'index' => null, 'heading' => 'h1', 'compact' => false])

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

    // Read from the files themselves. The terms point here for resolution, so
    // this has to be what the customer actually gets — not a house figure.
    $resolutions = $product->relationLoaded('files')
        ? $product->files->pluck('dpi')->filter()->unique()->sort()->values()
        : collect();

    $formats = $product->relationLoaded('files')
        ? $product->files->pluck('format')->filter()->unique()->map(fn ($f) => strtoupper($f))->values()
        : collect();
@endphp

<div {{ $attributes->class(['flex flex-col gap-4']) }}>
    @if ($index !== null)
        <div class="flex items-center gap-3">
            <span class="label">{{ str_pad((string) $index, 3, '0', STR_PAD_LEFT) }}</span>
            <span class="h-px flex-1 bg-rule"></span>
        </div>
    @endif

    <div>
        <{{ $heading }} @class(["text-balance", "text-xl sm:text-2xl" => $compact, "text-3xl sm:text-4xl" => ! $compact])>
            {{ $translation?->title }}
        </{{ $heading }}>

        @if ($translation?->subtitle)
            <p @class(["text-muted", "mt-1 text-sm" => $compact, "mt-2" => ! $compact])>{{ $translation->subtitle }}</p>
        @endif
    </div>

    @unless ($compact)
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

        @if ($resolutions->isNotEmpty())
            <dt class="label self-center">Resolution</dt>
            <dd>
                @if ($resolutions->count() === 1)
                    {{ $resolutions->first() }} DPI
                @else
                    {{ $resolutions->first() }}–{{ $resolutions->last() }} DPI
                @endif
                @if ($formats->isNotEmpty())
                    · {{ $formats->implode(', ') }}
                @endif
            </dd>
        @elseif ($formats->isNotEmpty())
            <dt class="label self-center">Format</dt>
            <dd>{{ $formats->implode(', ') }}</dd>
        @endif

        <dt class="label self-center">Delivery</dt>
        <dd>Instant download</dd>
    </dl>
    @endunless

    <p @class(["flex items-baseline gap-3 font-display", "text-xl" => $compact, "text-3xl" => ! $compact])>
        {{-- Full-contrast and in the display face: a price set in the UI sans
             at body size disappears beside the title (§12.4.4). --}}
        <span>{{ $price->format() }}</span>

        @if ($product->isOnSale())
            <span class="font-sans text-sm text-muted line-through">{{ $product->price()->format() }}</span>
            <span class="label text-accent">On sale</span>
        @endif
    </p>
</div>
