<?php

declare(strict_types=1);

namespace App\Services\Downloads;

use App\Models\ProductFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Hands a file over without moving its bytes through PHP.
 *
 * Local disk delegates to Nginx; object storage redirects to a short-lived
 * pre-signed URL. Either way the download limit was already enforced on our
 * server before this is reached — a pre-signed URL cannot count downloads, so
 * it must never be the link a customer holds. See §8.6.2.
 */
final class FileStreamer
{
    public function serve(ProductFile $file, string $filename): Response|RedirectResponse|BinaryFileResponse
    {
        return match ($file->disk) {
            'local', 'private' => $this->viaWebServer($file, $filename),
            default => $this->viaPresignedUrl($file, $filename),
        };
    }

    /**
     * PHP decides; Nginx transfers. Memory stays flat whatever the file size,
     * and a 200 MB bundle does not occupy a PHP worker for the whole download.
     */
    private function viaWebServer(ProductFile $file, string $filename): Response|BinaryFileResponse
    {
        $disk = Storage::disk($file->disk);

        // The built-in server and the test runner have no X-Accel-Redirect, so
        // fall back to a direct send where it would do nothing.
        if (! $this->webServerSupportsInternalRedirect()) {
            return response()->download($disk->path($file->path), $filename, [
                'Content-Type' => $file->mime_type,
            ]);
        }

        return response()->noContent(200, [
            'X-Accel-Redirect' => '/protected/'.$file->path,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $this->disposition($filename),
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Sixty seconds: long enough to download on a slow connection, far too
     * short to distribute. The counter was already spent getting here.
     */
    private function viaPresignedUrl(ProductFile $file, string $filename): RedirectResponse
    {
        $ttl = (int) config('store.download.presigned_ttl_seconds', 60);

        $url = Storage::disk($file->disk)->temporaryUrl(
            $file->path,
            now()->addSeconds($ttl),
            [
                // Without this the browser saves a file named after the UUID
                // on disk rather than something the customer recognises.
                'ResponseContentDisposition' => $this->disposition($filename),
                'ResponseContentType' => $file->mime_type,
            ],
        );

        return redirect()->away($url);
    }

    private function disposition(string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'download';

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            str_replace('"', '', $fallback),
            rawurlencode($filename),
        );
    }

    private function webServerSupportsInternalRedirect(): bool
    {
        $software = $_SERVER['SERVER_SOFTWARE'] ?? '';

        return is_string($software) && str_contains(strtolower($software), 'nginx');
    }
}
