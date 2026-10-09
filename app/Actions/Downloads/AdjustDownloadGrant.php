<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Models\DownloadGrant;
use App\Notifications\DownloadLinksReady;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * The three things an owner does to a single download link.
 *
 * Kept together because they share one rule: every one of them changes what a
 * paying customer can reach, so every one of them is audited with the person
 * who did it. See §8.5.
 */
final class AdjustDownloadGrant
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Stops the link at once, without touching the order. */
    public function revoke(DownloadGrant $grant, string $reason): void
    {
        $grant->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => auth()->id(),
            'revoke_reason' => $reason,
        ])->save();

        $this->audit->record('download.revoked', $grant, ['reason' => $reason]);
    }

    /** Gives it back, for a link revoked in error. */
    public function restore(DownloadGrant $grant): void
    {
        $grant->forceFill([
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'revoke_reason' => null,
        ])->save();

        $this->audit->record('download.restored', $grant);
    }

    /**
     * Pushes the expiry out from now, not from whenever it lapsed.
     *
     * Extending an expired link by a day from its old date would leave it
     * expired, which is the kind of help that generates a second email.
     */
    public function extend(DownloadGrant $grant, int $hours): void
    {
        $from = $grant->expires_at->isPast() ? Carbon::now() : $grant->expires_at;

        $grant->forceFill(['expires_at' => $from->copy()->addHours($hours)])->save();

        $this->audit->record('download.extended', $grant, [
            'hours' => $hours,
            'expires_at' => $grant->expires_at->toDateTimeString(),
        ]);
    }

    /**
     * A fresh token, a reset counter, and the new link in the customer's inbox.
     *
     * Emailing is not optional. The database holds only the hash, so the old
     * link dies the moment this runs — regenerating without sending would
     * leave a paying customer with nothing and no way to tell. See §8.2.
     */
    public function regenerate(DownloadGrant $grant): void
    {
        $token = IssueDownloadGrants::newToken();

        $grant->forceFill([
            'token_hash' => IssueDownloadGrants::hash($token),
            'download_count' => 0,
            'first_downloaded_at' => null,
            'last_downloaded_at' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'revoke_reason' => null,
        ])->save();

        $grant->plainToken = $token;

        $grant->loadMissing('order.customer');
        $grant->order->customer->notify(
            new DownloadLinksReady($grant->order, collect([$grant])),
        );

        $this->audit->record('download.regenerated', $grant);
    }
}
