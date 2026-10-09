<?php

declare(strict_types=1);

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('renders the coupon list and create pages', function (): void {
    $this->get('/admin/coupons')->assertOk();
    $this->get('/admin/coupons/create')->assertOk();
});

it('stores a code uppercase however it was typed', function (): void {
    livewire(CreateCoupon::class)
        ->fillForm([
            'code' => 'spring24',
            'type' => CouponType::Percent->value,
            'value' => '15',
            'applies_to' => CouponScope::All->value,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Lower case here would mean the storefront lookup never matches.
    expect(Coupon::firstOrFail()->code)->toBe('SPRING24');
});

it('takes a fixed discount in money and stores it in cents', function (): void {
    livewire(CreateCoupon::class)
        ->fillForm([
            'code' => 'FIVEOFF',
            'type' => CouponType::Fixed->value,
            'value' => '5.00',
            'applies_to' => CouponScope::All->value,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // The admin types dollars; nothing below this line ever sees a float.
    expect(Coupon::firstOrFail()->value)->toBe(500);
});

it('round-trips a percentage without multiplying it by a hundred', function (): void {
    $coupon = Coupon::factory()->percent(15)->create(['code' => 'FIFTEEN']);

    livewire(EditCoupon::class, ['record' => $coupon->getRouteKey()])
        ->assertFormSet(['value' => '15'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coupon->refresh()->value)->toBe(15);
});

it('round-trips a fixed amount as money', function (): void {
    $coupon = Coupon::factory()->fixed(750)->create(['code' => 'SEVENFIFTY']);

    livewire(EditCoupon::class, ['record' => $coupon->getRouteKey()])
        ->assertFormSet(['value' => '7.50'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coupon->refresh()->value)->toBe(750);
});

it('refuses a duplicate code', function (): void {
    Coupon::factory()->create(['code' => 'TAKEN']);

    livewire(CreateCoupon::class)
        ->fillForm([
            'code' => 'taken',
            'type' => CouponType::Percent->value,
            'value' => '10',
            'applies_to' => CouponScope::All->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('never lets a percentage exceed a hundred', function (): void {
    livewire(CreateCoupon::class)
        ->fillForm([
            'code' => 'TOOMUCH',
            'type' => CouponType::Percent->value,
            'value' => '150',
            'applies_to' => CouponScope::All->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['value']);
});

it('reports what each coupon has given away', function (): void {
    $coupon = Coupon::factory()->percent(10)->create(['code' => 'REPORTED']);
    $order = pendingOrder();

    $coupon->usages()->create([
        'order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'email_hash' => $order->email_hash,
        'discount_cents' => 450,
        'created_at' => now(),
    ]);

    $this->get('/admin/coupons')->assertOk()->assertSee('$4.50');
});
