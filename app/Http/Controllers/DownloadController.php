<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Downloads\IssueDownloadGrants;
use App\Models\DownloadGrant;
use App\Models\DownloadLog;
use App\Services\Downloads\FileStreamer;
use App\Support\BlindIndex;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a purchased file, or explains why it cannot.
 *
 * Every check happens here, on our server, before any byte is released —
 * including when the file itself lives in object storage. See §8.4 and §8.6.2.
 */
class DownloadController
{
    public function __invoke(Request $request, string $token, FileStreamer $streamer): Response
    {
        // One indexed lookup on the hash. The plaintext token is never stored.
        $grant = DownloadGrant::query()
            ->with('file')
            ->where('token_hash', IssueDownloadGrants::hash($token))
            ->first();

        if ($grant === null) {
            return $this->deny($request, null, 'not_found', 404);
        }

        if ($grant->isRevoked()) {
            return $this->deny($request, $grant, 'revoked', 410);
        }

        if ($grant->isExpired()) {
            return $this->deny($request, $grant, 'expired', 410);
        }

        if ($grant->isExhausted()) {
            return $this->deny($request, $grant, 'limit', 429);
        }

        // Counted before the transfer begins. A download that fails halfway is
        // the customer's connection, not a free extra attempt.
        $grant->increment('download_count');
        $grant->forceFill([
            'first_downloaded_at' => $grant->first_downloaded_at ?? now(),
            'last_downloaded_at' => now(),
        ])->save();

        $this->log($request, $grant, 'ok');

        return $streamer->serve($grant->file, $this->filenameFor($grant));
    }

    private function deny(Request $request, ?DownloadGrant $grant, string $status, int $code): Response
    {
        if ($grant !== null) {
            $this->log($request, $grant, $status);
        }

        // Expired and exhausted are recoverable without a support ticket: the
        // renewal only ever emails the original buyer. See §8.4.
        $recoverable = in_array($status, ['expired', 'limit'], true);

        return response()->view('storefront.download-denied', [
            'reason' => $status,
            'grant' => $recoverable ? $grant : null,
        ], $code);
    }

    private function log(Request $request, DownloadGrant $grant, string $status): void
    {
        DownloadLog::create([
            'download_grant_id' => $grant->getKey(),
            'ip_hash' => BlindIndex::ip($request->ip()),
            'user_agent_hash' => BlindIndex::userAgent($request->userAgent()),
            'range_header' => $request->header('Range'),
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    /** What the customer sees in their downloads folder. */
    private function filenameFor(DownloadGrant $grant): string
    {
        $file = $grant->file;
        $extension = pathinfo($file->original_filename, PATHINFO_EXTENSION);
        $base = $file->display_name ?: pathinfo($file->original_filename, PATHINFO_FILENAME);

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }
}
