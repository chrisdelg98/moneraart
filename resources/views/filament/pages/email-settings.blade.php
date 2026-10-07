<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">Deliverability</x-slot>
            <x-slot name="description">
                A download email in the spam folder is an undelivered product.
            </x-slot>

            <ul class="space-y-3">
                @foreach ($this->checks() as $check)
                    <li class="flex items-start gap-3">
                        <x-filament::icon
                            :icon="$check['ok'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle'"
                            @class(['mt-0.5 h-5 w-5 shrink-0', 'text-success-500' => $check['ok'], 'text-warning-500' => ! $check['ok']]) />
                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">{{ $check['label'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $check['detail'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
