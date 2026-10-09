<?php

declare(strict_types=1);

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the audit log', function (): void {
    $this->get('/admin/audit-logs')->assertOk();
});

it('cannot be written to from the screen it is read on', function (): void {
    $entry = AuditLog::create(['action' => 'order.approved', 'created_at' => now()]);

    // A log that can be edited where it is read is not evidence of anything.
    expect(AuditLogResource::canCreate())->toBeFalse()
        ->and(AuditLogResource::canEdit($entry))->toBeFalse()
        ->and(AuditLogResource::canDelete($entry))->toBeFalse()
        ->and(AuditLogResource::canDeleteAny())->toBeFalse();
});

it('reads an action back as a sentence, with who did it', function (): void {
    app(AuditLogger::class)->record('customer.email_revealed');

    livewire(ListAuditLogs::class)
        ->assertSee('Revealed a customer address')
        ->assertSee($this->user->name);
});

it('calls an entry with no user automatic, not unknown', function (): void {
    auth()->logout();
    app(AuditLogger::class)->record('order.refunded');
    $this->actingAs($this->user);

    // A job did it. That is a different fact from "we have no idea".
    livewire(ListAuditLogs::class)->assertSee('Automatic');
});

it('records a settings change by key, never by value', function (): void {
    Settings::set('paypal.live.client_secret', 'super-secret-value');

    $entry = AuditLog::where('action', 'settings.updated')->firstOrFail();

    // An audit trail that stores secrets is a second place to steal them.
    expect($entry->metadata['key'])->toBe('paypal.live.client_secret')
        ->and($entry->metadata['secret'])->toBeTrue()
        ->and(json_encode($entry->metadata))->not->toContain('super-secret-value');
});

it('filters by action', function (): void {
    app(AuditLogger::class)->record('order.approved');
    app(AuditLogger::class)->record('customer.email_revealed');

    $approved = AuditLog::where('action', 'order.approved')->get();
    $revealed = AuditLog::where('action', 'customer.email_revealed')->get();

    // On the records, not on the text: every label also appears inside the
    // filter's own dropdown, so assertDontSee could never pass.
    livewire(ListAuditLogs::class)
        ->filterTable('action', 'order.approved')
        ->assertCanSeeTableRecords($approved)
        ->assertCanNotSeeTableRecords($revealed);
});
