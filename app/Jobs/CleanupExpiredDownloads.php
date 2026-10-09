<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DownloadGrant;
use App\Models\DownloadLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Nightly housekeeping for delivery.
 *
 * Grants are kept for ninety days past expiry, not deleted the moment they
 * lapse: the renewal flow needs the row to know whose files to reissue, and a
 * customer who comes back a month later is the ordinary case. Logs are kept a
 * year, because they are the evidence in a "I never got it" dispute.
 *
 * Both windows come from §8.5 and §9.5.
 */
class CleanupExpiredDownloads implements ShouldQueue
{
    use Queueable;

    private const GRANT_DAYS = 90;

    private const LOG_DAYS = 365;

    public function handle(): void
    {
        // Deleted in chunks: a year of logs on a busy month is not a row count
        // worth loading into memory, or holding one lock over.
        $logs = DownloadLog::query()
            ->where('created_at', '<', now()->subDays(self::LOG_DAYS))
            ->limit(1000)
            ->delete();

        // Only grants with no logs left. download_logs cascades on the grant,
        // so deleting a ninety-day-old grant that was downloaded would take
        // its logs with it, months before their own year is up.
        $grants = DownloadGrant::query()
            ->where('expires_at', '<', now()->subDays(self::GRANT_DAYS))
            ->whereDoesntHave('logs')
            ->limit(1000)
            ->delete();

        if ($logs === 0 && $grants === 0) {
            return;
        }

        Log::channel(config('logging.default'))->info('downloads.pruned', [
            'grants' => $grants,
            'logs' => $logs,
        ]);
    }
}
