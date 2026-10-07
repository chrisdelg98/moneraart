<?php

declare(strict_types=1);

use App\Actions\Orders\CompleteOrder;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\AuditLog;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DownloadLinksReady;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);
    $this->actingAs(User::factory()->create());
});

it('lists orders', function (): void {
    $order = paidOrder();

    livewire(ListOrders::class)->assertCanSeeTableRecords([$order]);
});

it('never shows a customer address in the list', function (): void {
    $order = paidOrder();

    // The domain is enough to scan by; the address is revealed only on purpose
    // and only with an audit entry. See §9.1.
    livewire(ListOrders::class)
        ->assertDontSee('buyer@example.com')
        ->assertSee('•••@example.com');

    expect($order->customer->email)->toBe('buyer@example.com');
});

it('shows an order with its payment and downloads', function (): void {
    $order = paidOrder();
    app(CompleteOrder::class)($order);

    livewire(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSuccessful()
        ->assertSee($order->number);
});

it('approves an order in manual review and delivers it', function (): void {
    $order = paidOrder();
    $order->forceFill([
        'status' => OrderStatus::ManualReview,
        'manual_review_reason' => 'PayPal captured 1.00; the order total is 5.99.',
    ])->save();

    livewire(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('approve', data: ['reason' => 'Buyer paid the rest by bank transfer.']);

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(DownloadGrant::count())->toBe(1);

    Notification::assertSentTo($order->customer, DownloadLinksReady::class);
});

it('records who approved an order and why', function (): void {
    $order = paidOrder();
    $order->forceFill(['status' => OrderStatus::ManualReview])->save();

    livewire(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('approve', data: ['reason' => 'Verified with PayPal by phone.']);

    $log = AuditLog::where('action', 'order.approved')->firstOrFail();

    expect($log->user_id)->toBe(auth()->id())
        ->and($log->metadata['reason'])->toBe('Verified with PayPal by phone.')
        // The audit trail must not become a second store of personal data.
        ->and($log->ip_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('requires a reason before approving', function (): void {
    $order = paidOrder();
    $order->forceFill(['status' => OrderStatus::ManualReview])->save();

    livewire(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('approve', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($order->fresh()->status)->toBe(OrderStatus::ManualReview);
});

it('offers approval only where it applies', function (): void {
    $completed = paidOrder();
    app(CompleteOrder::class)($completed);

    livewire(ViewOrder::class, ['record' => $completed->getKey()])
        ->assertActionHidden('approve')
        ->assertActionVisible('reissue');
});

it('reissues links and invalidates the old ones', function (): void {
    $order = paidOrder();
    app(CompleteOrder::class)($order);

    $original = DownloadGrant::firstOrFail();

    livewire(ViewOrder::class, ['record' => $order->getKey()])->callAction('reissue');

    expect(DownloadGrant::where('id', $original->id)->exists())->toBeFalse()
        ->and(DownloadGrant::count())->toBe(1)
        ->and(AuditLog::where('action', 'order.reissue')->exists())->toBeTrue();

    Notification::assertSentToTimes($order->customer, DownloadLinksReady::class, 2);
});

it('audits revealing a customer address', function (): void {
    $order = paidOrder();

    livewire(ViewOrder::class, ['record' => $order->getKey()])->callAction('reveal');

    expect(AuditLog::where('action', 'customer.email_revealed')->count())->toBe(1);
});

it('does not let an order be created by hand', function (): void {
    // An order is a record of what happened, not something typed in.
    expect(OrderResource::canCreate())->toBeFalse();
});

it('filters down to the orders that need attention', function (): void {
    $fine = paidOrder();
    $flagged = paidOrder();
    $flagged->forceFill(['status' => OrderStatus::ManualReview])->save();

    livewire(ListOrders::class)
        ->filterTable('needs_attention')
        ->assertCanSeeTableRecords([$flagged])
        ->assertCanNotSeeTableRecords([$fine]);
});

it('surfaces orders stuck at pending', function (): void {
    $stuck = paidOrder();
    $stuck->forceFill(['status' => OrderStatus::Pending])->save();
    Order::where('id', $stuck->id)->update(['created_at' => now()->subHour()]);

    livewire(ListOrders::class)
        ->filterTable('stuck')
        ->assertCanSeeTableRecords([$stuck]);
});
