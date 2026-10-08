@props(['paid' => true])

@php
    $items = [
        ['Instant download', 'M12 3v12m0 0 4-4m-4 4-4-4 M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
        ['No account needed', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M4 21a8 8 0 0 1 16 0'],
    ];

    if ($paid) {
        // A card is the point: PayPal processes it, but no account is needed
        // to use one, and saying only "PayPal" turns card payers away.
        $items[] = ['Secure payment by card or PayPal', 'M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M3 10h18 M7 15h3'];
    }
@endphp

{{-- The three things a first-time buyer wants to know before they commit, in
     the order they worry about them. --}}
<ul {{ $attributes->class(['space-y-2.5 text-sm text-muted']) }}>
    @foreach ($items as [$label, $path])
        <li class="flex items-center gap-2.5">
            <svg class="h-4 w-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="{{ $path }}" />
            </svg>
            {{ $label }}
        </li>
    @endforeach
</ul>
