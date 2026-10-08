@props(['step'])

{{--
    Where the visitor is in a flow that is only ever three steps. It echoes the
    rail in "how it works" on the home page, so the promise made there is the
    same shape as the checkout that keeps it.
--}}
<ol {{ $attributes->class(['flex flex-wrap items-center gap-x-3 gap-y-2 text-sm']) }}>
    @foreach (['Cart', 'Details', 'Download'] as $i => $label)
        @php $n = $i + 1; @endphp

        <li class="flex items-center gap-3" @if ($n === $step) aria-current="step" @endif>
            @if ($i > 0)
                <span class="h-px w-6 bg-rule" aria-hidden="true"></span>
            @endif

            <span @class([
                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-[0.6875rem]',
                'border-accent bg-accent text-paper' => $n === $step,
                'border-ink bg-ink text-paper' => $n < $step,
                'rule text-muted' => $n > $step,
            ])>
                @if ($n < $step)
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m5 13 4 4L19 7" />
                    </svg>
                @else
                    {{ $n }}
                @endif
            </span>

            <span @class(['text-muted' => $n !== $step])>{{ $label }}</span>
        </li>
    @endforeach
</ol>
