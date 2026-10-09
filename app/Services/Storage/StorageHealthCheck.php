<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Proves the configured storage actually works, before anything is sold on it.
 *
 * Four checks, run against a throwaway object that is deleted afterwards. The
 * last is the one that matters: a bucket anyone can read makes the download
 * limit decorative, because the file is reachable without a token at all.
 * See §8.6.2.
 */
final class StorageHealthCheck
{
    public function __construct(private readonly StorageManager $storage) {}

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function run(): array
    {
        if (! $this->storage->isRemote()) {
            return $this->localChecks();
        }

        return $this->remoteChecks();
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    private function localChecks(): array
    {
        $disk = Storage::disk('private');
        $probe = 'health/'.Str::uuid().'.txt';

        try {
            $disk->put($probe, 'ok');
            $writable = $disk->get($probe) === 'ok';
            $disk->delete($probe);
        } catch (Throwable $e) {
            return [[
                'label' => 'Private storage is writable',
                'ok' => false,
                'detail' => $e->getMessage(),
            ]];
        }

        return [
            [
                'label' => 'Private storage is writable',
                'ok' => $writable,
                'detail' => $writable ? 'Files can be written and read back.' : 'Write succeeded but read did not.',
            ],
            [
                'label' => 'Files are served by this server',
                'ok' => true,
                'detail' => 'Every download is authorised and counted here before a byte is sent.',
            ],
        ];
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    private function remoteChecks(): array
    {
        $name = $this->storage->diskName();
        $probe = 'health/'.Str::uuid().'.txt';
        $body = 'monera-'.Str::random(16);

        // 1. Credentials. Everything after this depends on them.
        if (! $this->storage->hasCredentials()) {
            return [[
                'label' => 'Credentials are complete',
                'ok' => false,
                'detail' => 'Account ID, access key, secret and bucket are all required.',
            ]];
        }

        $checks = [[
            'label' => 'Credentials are complete',
            'ok' => true,
            'detail' => 'All four values are set.',
        ]];

        $disk = Storage::disk($name);

        // 2. Writable.
        try {
            $disk->put($probe, $body);
            $checks[] = ['label' => 'The bucket accepts uploads', 'ok' => true, 'detail' => 'Wrote a test object.'];
        } catch (Throwable $e) {
            $checks[] = ['label' => 'The bucket accepts uploads', 'ok' => false, 'detail' => $e->getMessage()];

            return $checks;
        }

        try {
            // 3. A pre-signed URL that actually serves the bytes back. This is
            //    the mechanism every customer download depends on.
            $signed = $disk->temporaryUrl($probe, now()->addSeconds(60));
            $fetched = Http::timeout(10)->get($signed);

            $checks[] = [
                'label' => 'Signed links work',
                'ok' => $fetched->successful() && $fetched->body() === $body,
                'detail' => $fetched->successful()
                    ? 'A 60-second link returned the file.'
                    : 'The signed link returned HTTP '.$fetched->status().'.',
            ];

            // 4. The one that matters. If the object is readable without the
            //    signature, the bucket is public: the counter, the expiry and
            //    revocation are all decorative, because the file needs no
            //    token at all. See §8.6.2.
            $unsigned = Http::timeout(10)->get(Str::before($signed, '?'));

            $checks[] = [
                'label' => 'The bucket is not public',
                'ok' => ! $unsigned->successful(),
                'detail' => $unsigned->successful()
                    ? 'Anyone can read this bucket without a signature. Download limits cannot be enforced — turn off public access before selling from it.'
                    : 'Unsigned requests are refused, so the download limit is real.',
            ];
        } catch (Throwable $e) {
            $checks[] = ['label' => 'Signed links work', 'ok' => false, 'detail' => $e->getMessage()];
        } finally {
            // Never leave the probe behind, whatever failed.
            try {
                $disk->delete($probe);
            } catch (Throwable) {
            }
        }

        return $checks;
    }
}
