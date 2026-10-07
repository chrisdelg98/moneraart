<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <x-slot name="description">
                Checked against PayPal, not against what is typed above.
            </x-slot>

            <ul class="space-y-3">
                @foreach ($this->healthChecks() as $check)
                    <li class="flex items-start gap-3">
                        @if ($check['ok'])
                            <x-filament::icon icon="heroicon-o-check-circle"
                                              class="mt-0.5 h-5 w-5 shrink-0 text-success-500" />
                        @else
                            <x-filament::icon icon="heroicon-o-exclamation-circle"
                                              class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                        @endif

                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">{{ $check['label'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $check['detail'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Where do I find these keys?</x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <ol>
                    <li>Sign in at <strong>developer.paypal.com</strong> with your PayPal business account.</li>
                    <li>Go to <strong>Apps &amp; Credentials</strong>.</li>
                    <li>Pick the <strong>Sandbox</strong> or <strong>Live</strong> tab — it must match the mode above.</li>
                    <li>Create an app, or open an existing one.</li>
                    <li>Copy the <strong>Client ID</strong> and <strong>Secret</strong> into the fields above.</li>
                </ol>

                <p>
                    That is everything. You do not need to register a webhook or copy an ID back
                    here &mdash; pressing <strong>Save and enable payments</strong> registers it with
                    PayPal for you, and repairs it later if its event list ever drifts.
                </p>

                <p class="text-gray-500">
                    Running more than one store on the same PayPal account? Create a separate app
                    per site rather than reusing one set of keys, so each store receives only its
                    own events and either can be revoked on its own.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
