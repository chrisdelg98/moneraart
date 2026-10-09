<?php

declare(strict_types=1);

use App\Actions\Downloads\AdjustDownloadGrant;
use App\Actions\Downloads\IssueDownloadGrants;
use App\Filament\Resources\Downloads\DownloadResource;
use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Models\AuditLog;
use App\Models\DownloadGrant;
use App\Models\User;
use App\Notifications\DownloadLinksReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('private');
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

/** A live download link on a real paid order. */
function liveGrant(): DownloadGrant
{
    return app(IssueDownloadGrants::class)(paidOrder())->first()->refresh();
}

it('renders the downloads list', function (): void {
    $this->get('/admin/downloads')->assertOk();
});

it('never offers to delete a link', function (): void {
    $grant = liveGrant();

    // Deleting a grant cascades to its download logs — the evidence in an
    // "I never received it" dispute. Revoke stops the link and keeps both.
    expect(DownloadResource::canCreate())->toBeFalse()
        ->and(DownloadResource::canDelete($grant))->toBeFalse()
        ->and(DownloadResource::canDeleteAny())->toBeFalse();
});

it('revokes a link with a reason, and lets it back', function (): void {
    $grant = liveGrant();

    livewire(ListDownloads::class)
        ->callTableAction('revoke', $grant, ['reason' => 'Shared publicly']);

    expect($grant->refresh()->isRevoked())->toBeTrue()
        ->and($grant->revoke_reason)->toBe('Shared publicly')
        ->and(AuditLog::where('action', 'download.revoked')->exists())->toBeTrue();

    livewire(ListDownloads::class)->callTableAction('restore', $grant);

    expect($grant->refresh()->isRevoked())->toBeFalse();
});

it('extends an expired link from now, not from when it lapsed', function (): void {
    $grant = liveGrant();
    $grant->forceFill(['expires_at' => now()->subDays(10)])->save();

    app(AdjustDownloadGrant::class)->extend($grant, 24);

    // Adding a day to a date ten days gone leaves it expired, which is the
    // kind of help that generates a second email.
    expect($grant->refresh()->isExpired())->toBeFalse()
        ->and($grant->expires_at->isAfter(now()->addHours(23)))->toBeTrue();
});

it('extends a live link from its own expiry', function (): void {
    $grant = liveGrant();
    $original = $grant->expires_at->copy();

    app(AdjustDownloadGrant::class)->extend($grant, 24);

    expect($grant->refresh()->expires_at->equalTo($original->addHours(24)))->toBeTrue();
});

it('emails the new link whenever one is regenerated', function (): void {
    $grant = liveGrant();
    $oldHash = $grant->token_hash;
    $grant->forceFill(['download_count' => 5])->save();

    app(AdjustDownloadGrant::class)->regenerate($grant->refresh());

    // The database holds only the hash, so the old link dies the moment this
    // runs. Not sending would leave a paying customer with nothing.
    Notification::assertSentTo($grant->order->customer, DownloadLinksReady::class);

    expect($grant->refresh()->token_hash)->not->toBe($oldHash)
        ->and($grant->download_count)->toBe(0)
        ->and($grant->isUsable())->toBeTrue()
        ->and(AuditLog::where('action', 'download.regenerated')->exists())->toBeTrue();
});

it('shows what is wrong with a link that cannot be used', function (): void {
    $grant = liveGrant();
    $grant->forceFill(['download_count' => $grant->max_downloads])->save();

    livewire(ListDownloads::class)->assertSee('Used up');
});
