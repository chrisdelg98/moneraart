{{--
    Deliberately loud. This panel only renders when money is involved and
    something that should have run has not. See the widget for what it reads.
--}}
<x-filament-widgets::widget>
    <div class="rounded-xl border border-danger-300 bg-danger-50 p-5 dark:border-danger-700 dark:bg-danger-950/40">
        <div class="flex items-start gap-3">
            <x-filament::icon
                icon="heroicon-o-exclamation-triangle"
                class="mt-0.5 h-6 w-6 shrink-0 text-danger-600 dark:text-danger-400"
            />

            <div class="min-w-0 space-y-4">
                <h2 class="text-base font-semibold text-danger-900 dark:text-danger-200">
                    Background work is not running
                </h2>

                @foreach ($this->getFindings() as $finding)
                    <div>
                        <p class="text-sm font-medium text-danger-900 dark:text-danger-200">
                            {{ $finding['title'] }}
                        </p>
                        <p class="mt-1 text-sm text-danger-800 dark:text-danger-300">
                            {{ $finding['body'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
