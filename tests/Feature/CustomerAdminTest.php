<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

it('renders the customer list', function (): void {
    $this->get('/admin/customers')->assertOk();
});

it('never prints an address on the list', function (): void {
    paidOrder();

    // The domain is enough to recognise a row by; the address is not shown
    // until someone asks for it and the asking is recorded. See §9.1.
    livewire(ListCustomers::class)
        ->assertSee('example.com')
        ->assertDontSee('buyer@example.com');
});

it('finds a customer by their whole address, and only their whole address', function (): void {
    paidOrder();

    // The index is an HMAC, so a partial search is not a missing feature —
    // it would require storing the address in clear.
    livewire(ListCustomers::class)
        ->filterTable('email', ['email' => 'buyer@example.com'])
        ->assertCanSeeTableRecords(Customer::all());

    livewire(ListCustomers::class)
        ->filterTable('email', ['email' => 'buyer@'])
        ->assertCanNotSeeTableRecords(Customer::all());
});

it('records an audit entry when an address is revealed', function (): void {
    paidOrder();
    $customer = Customer::firstOrFail();

    livewire(ViewCustomer::class, ['record' => $customer->getRouteKey()])
        ->callAction('reveal');

    expect(AuditLog::where('action', 'customer.email_revealed')->count())->toBe(1);
});

it('counts a paid order towards the customer totals', function (): void {
    $order = paidOrder(1500);
    $order->customer->recalculateOrderTotals();

    expect($order->customer->refresh()->orders_count)->toBe(1)
        ->and($order->customer->lifetime_value_cents)->toBe(1500)
        ->and($order->customer->last_order_at)->not->toBeNull();
});

it('takes a refunded order back out of the totals', function (): void {
    $order = paidOrder(1500);
    $order->customer->recalculateOrderTotals();

    $order->forceFill(['status' => OrderStatus::Refunded, 'refunded_at' => now()])->save();
    $order->customer->recalculateOrderTotals();

    // Money that came back was never lifetime value.
    expect($order->customer->refresh()->orders_count)->toBe(0)
        ->and($order->customer->lifetime_value_cents)->toBe(0);
});

it('counts one sale when both payment paths reach the same order', function (): void {
    $order = paidOrder(1500);

    // Recomputed rather than incremented: the capture and the webhook both
    // run for a normal purchase.
    $order->customer->recalculateOrderTotals();
    $order->customer->recalculateOrderTotals();

    expect($order->customer->refresh()->orders_count)->toBe(1)
        ->and($order->customer->lifetime_value_cents)->toBe(1500);
});
