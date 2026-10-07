<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Downloads\IssueDownloadGrants;
use App\Models\DownloadGrant;
use App\Notifications\DownloadLinksReady;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Self-service renewal of an expired or exhausted link.
 *
 * Safe to expose without authentication because the new links are emailed to
 * the original buyer, never shown to whoever pressed the button. A stranger
 * holding the URL gains nothing. See §8.4.
 */
class RenewDownloadController
{
    public function __invoke(DownloadGrant $grant, IssueDownloadGrants $issue): RedirectResponse
    {
        $order = $grant->order;

        // A revoked grant was revoked on purpose — usually a refund.
        if ($grant->isRevoked() || ! $order->status->isFulfillable()) {
            return back()->with('error', 'Those files are no longer available.');
        }

        DB::transaction(function () use ($order): void {
            // Replacing every grant on the order keeps one email holding one
            // consistent set of links.
            $order->loadMissing('items');
            DownloadGrant::where('order_id', $order->getKey())->delete();
        });

        $grants = $issue($order->fresh());

        $order->customer->notify(new DownloadLinksReady($order, $grants));

        return back()->with('status', 'Sent. Check your email in a moment.');
    }
}
