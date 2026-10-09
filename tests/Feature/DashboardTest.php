<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\SchedulerHealth;
use App\Filament\Widgets\StoreOverview;
use App\Models\Order;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

/** An order that has actually been paid for, at a given moment. */
function paidAt(string $when, int $cents = 1000): Order
{
    $order = pendingOrder($cents);

    $order->forceFill([
        'status' => OrderStatus::Completed,
        'paid_at' => now()->parse($when),
        'total_cents' => $cents,
    ])->save();

    return $order->refresh();
}

it('renders the dashboard', function (): void {
    $this->get('/admin')->assertOk();
});

it('counts today\'s revenue from what was actually paid', function (): void {
    paidAt('today', 1500);
    paidAt('today', 500);
    paidAt('-3 days', 9900);

    // A pending order is not revenue, however much it is for.
    pendingOrder(50000);

    livewire(StoreOverview::class)
        ->assertSee('$20.00')
        // $520.00 is what today would read if a pending order counted.
        ->assertDontSee('$520.00');
});

it('leaves a refunded order out of revenue', function (): void {
    $refunded = paidAt('today', 4000);
    $refunded->forceFill(['status' => OrderStatus::Refunded, 'refunded_at' => now()])->save();

    paidAt('today', 1000);

    // Money that came back was never revenue.
    livewire(StoreOverview::class)->assertSee('$10.00')->assertDontSee('$50.00');
});

it('shows the manual review queue only when something is in it', function (): void {
    // An empty table trains the eye to skip the place the urgent thing lands.
    expect(NeedsAttention::canView())->toBeFalse();

    pendingOrder()->forceFill([
        'status' => OrderStatus::ManualReview,
        'paid_at' => now(),
        'manual_review_reason' => 'PayPal captured 0.01; the order total is 14.38.',
    ])->save();

    expect(NeedsAttention::canView())->toBeTrue();

    livewire(NeedsAttention::class)
        ->assertSee('Needs your attention')
        ->assertSee('PayPal captured 0.01');
});

it('lists recent orders whatever became of them', function (): void {
    $failed = pendingOrder();
    $failed->forceFill(['status' => OrderStatus::Failed])->save();

    // A run of failures is the thing worth noticing, and a list of successes
    // only would hide exactly that.
    livewire(RecentOrders::class)
        ->assertSee($failed->number)
        ->assertSee(OrderStatus::Failed->label());
});

it('never prints a customer address on the dashboard', function (): void {
    paidAt('today');

    // The address is decrypted on the order itself, behind an audited reveal.
    livewire(RecentOrders::class)->assertDontSee('buyer@example.com');
});

it('stays quiet while the scheduler is keeping up', function (): void {
    paidAt('today');
    pendingOrder();

    expect(SchedulerHealth::canView())->toBeFalse();
});

it('says so when orders are not being reconciled', function (): void {
    // A cron that was never installed looks exactly like one that works,
    // until an order sits pending for days.
    pendingOrder()->forceFill(['created_at' => now()->subDays(4)])->save();

    expect(SchedulerHealth::canView())->toBeTrue();

    livewire(SchedulerHealth::class)
        ->assertSee('Background work is not running')
        ->assertSee('left pending past the cut-off');
});

it('says so when a verified payment was never applied', function (): void {
    WebhookEvent::create([
        'provider' => 'paypal',
        'event_id' => 'WH-TEST-1',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'signature_verified' => true,
        'payload' => [],
        'status' => 'received',
        'received_at' => now()->subHours(2),
    ]);

    // PayPal confirmed it and nothing acted on it. Someone has paid.
    livewire(SchedulerHealth::class)->assertSee('not applied');
});
