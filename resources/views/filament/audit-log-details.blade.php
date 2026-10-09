{{-- The raw metadata, for the cases the one-line summary cannot carry. --}}
<div class="space-y-3 text-sm">
    <dl class="space-y-2">
        @foreach ($entry->metadata ?? [] as $key => $value)
            <div class="flex gap-3">
                <dt class="w-32 shrink-0 text-gray-500 dark:text-gray-400">
                    {{ Str::headline($key) }}
                </dt>
                <dd class="min-w-0 flex-1 break-words text-gray-950 dark:text-white">
                    {{ is_scalar($value) ? $value : json_encode($value, JSON_PRETTY_PRINT) }}
                </dd>
            </div>
        @endforeach
    </dl>

    {{-- Hashed on purpose: an audit trail must not become a second store of
         personal data. It still proves two entries came from one place. --}}
    <p class="border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
        Origin fingerprint: {{ Str::limit($entry->ip_hash, 16) }}
    </p>
</div>
