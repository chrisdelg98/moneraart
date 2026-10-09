<?php

declare(strict_types=1);

use App\Actions\Downloads\IssueDownloadGrants;
use App\Jobs\CleanupExpiredDownloads;
use App\Models\DownloadGrant;
use App\Models\DownloadLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
});

/** A grant on a real order, with its expiry moved to a given moment. */
function grantExpiring(string $when): DownloadGrant
{
    $order = paidOrder();
    $grant = app(IssueDownloadGrants::class)($order)->first();

    $grant->forceFill(['expires_at' => now()->parse($when)])->save();

    return $grant->refresh();
}

it('keeps a grant that has merely expired', function (): void {
    grantExpiring('-10 days');

    (new CleanupExpiredDownloads)->handle();

    // The renewal flow needs the row to know whose files to reissue, and
    // coming back a month later is the ordinary case.
    expect(DownloadGrant::count())->toBe(1);
});

it('deletes a grant ninety days past expiry', function (): void {
    grantExpiring('-91 days');

    (new CleanupExpiredDownloads)->handle();

    expect(DownloadGrant::count())->toBe(0);
});

it('never deletes a grant whose logs are still inside their year', function (): void {
    $grant = grantExpiring('-91 days');

    DownloadLog::create([
        'download_grant_id' => $grant->id,
        'ip_hash' => str_repeat('a', 64),
        'status' => 'served',
        'created_at' => now()->subDays(30),
    ]);

    (new CleanupExpiredDownloads)->handle();

    // download_logs cascades on the grant, so deleting it here would destroy
    // the evidence in an "I never received it" dispute.
    expect(DownloadGrant::count())->toBe(1)
        ->and(DownloadLog::count())->toBe(1);
});

it('prunes a log past its year, and then the grant becomes collectable', function (): void {
    $grant = grantExpiring('-400 days');

    DownloadLog::create([
        'download_grant_id' => $grant->id,
        'ip_hash' => str_repeat('a', 64),
        'status' => 'served',
        'created_at' => now()->subDays(400),
    ]);

    (new CleanupExpiredDownloads)->handle();
    expect(DownloadLog::count())->toBe(0);

    // The next night's run takes the grant, now that nothing depends on it.
    (new CleanupExpiredDownloads)->handle();
    expect(DownloadGrant::count())->toBe(0);
});
