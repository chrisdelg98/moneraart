@php
    $notice = session('notice');
@endphp

@if (is_array($notice) && isset($notice['text']))
    {{--
        A transient cart message.

        It is rendered server-side rather than built in JavaScript, so it is in
        the page at first paint and reaches a screen reader through a live
        region that already exists. The dismissal is the only part that needs
        a script; without one it simply stays, which is not a failure.
    --}}
    <div class="toast" data-toast role="{{ $notice['isError'] ? 'alert' : 'status' }}"
         aria-live="{{ $notice['isError'] ? 'assertive' : 'polite' }}">
        <span @class(['mt-0.5 shrink-0', 'text-accent' => $notice['isError'], 'text-muted' => ! $notice['isError']])>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                @if ($notice['isError'])
                    <circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" />
                @else
                    <circle cx="12" cy="12" r="9" /><path d="m8.5 12.5 2.5 2.5 4.5-5" />
                @endif
            </svg>
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm leading-snug">{{ $notice['text'] }}</p>

            @isset($notice['url'])
                <a href="{{ $notice['url'] }}"
                   class="mt-1 inline-block text-sm text-accent underline underline-offset-4">
                    {{ $notice['label'] ?? 'View cart' }}
                </a>
            @endisset
        </div>

        <button type="button" data-toast-close
                class="-mr-1 -mt-1 flex h-8 w-8 shrink-0 items-center justify-center text-muted hover:text-ink"
                aria-label="Dismiss">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                <path d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>
    </div>
@endif
