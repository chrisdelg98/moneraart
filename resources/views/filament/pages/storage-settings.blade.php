<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <x-slot name="description">
                Checked against the storage itself, not against what is typed above.
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

        @if (count($this->fileCounts()) > 1)
            {{-- Only worth saying once files live in two places. --}}
            <x-filament::section>
                <x-slot name="heading">Where your files are</x-slot>
                <x-slot name="description">
                    Each file remembers its own location, so both sets keep working.
                </x-slot>

                <ul class="space-y-2 text-sm">
                    @foreach ($this->fileCounts() as $disk => $total)
                        <li class="flex justify-between">
                            <span>{{ $disk === 'private' ? 'This server' : strtoupper($disk) }}</span>
                            <span class="text-gray-500">{{ $total }} {{ Str::plural('file', $total) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Why R2, and what to set up</x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>
                    On this server, every download passes through PHP: your server moves
                    the bytes, and a large file occupies a worker for the whole transfer.
                    R2 charges nothing for bandwidth and sends the file itself, so your
                    server only issues a redirect.
                </p>

                <ol>
                    <li>In Cloudflare, open <strong>R2</strong> and create a bucket.</li>
                    <li>Leave <strong>public access turned off</strong>. This matters more than
                        anything else on this page.</li>
                    <li>Create an <strong>API token</strong> with read and write on that bucket.</li>
                    <li>Paste the account ID, bucket name, key and secret above, then press
                        <strong>Test connection</strong>.</li>
                </ol>

                <p>
                    A public bucket makes the download limit meaningless: the file would be
                    reachable by its plain URL, with no token, no expiry and no way to revoke
                    it. The test above checks for exactly that and refuses to save if it finds it.
                </p>

                <p class="text-gray-500">
                    Switching is safe at any time. Files already uploaded keep their own
                    location and keep downloading; only new uploads follow this setting.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
