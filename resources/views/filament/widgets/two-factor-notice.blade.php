{{-- Quieter than the red panel above it: this is a standing condition, not
     something that went wrong today. A warning that shouts every day stops
     being read by the end of the week. --}}
<x-filament-widgets::widget>
    <div class="rounded-xl border border-warning-300 bg-warning-50 px-5 py-4 dark:border-warning-700/60 dark:bg-warning-950/30">
        <div class="flex items-start gap-3">
            <x-filament::icon
                icon="heroicon-o-lock-open"
                class="mt-0.5 h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400"
            />

            <div class="min-w-0">
                <p class="text-sm font-medium text-warning-900 dark:text-warning-200">
                    Two-factor authentication is off
                </p>

                <p class="mt-1 text-sm text-warning-800 dark:text-warning-300">
                    One password stands between this panel and everything in it:
                    customer addresses, payment keys, and the files you sell.
                    Turning it on is the single largest thing you can do for the
                    security of the store.
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
