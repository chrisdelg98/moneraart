<?php

declare(strict_types=1);

use App\Filament\Widgets\TwoFactorNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('ships available and switched off', function (): void {
    $user = User::factory()->create();

    // Not required, deliberately: forcing a factor nobody has set up is how
    // a person locks themselves out of their own store.
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($this->actingAs($user)->get('/admin')->status())->toBe(200);
});

it('offers somewhere to turn it on', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/profile')->assertOk();
});

it('nags until the operator looking at it has enabled it', function (): void {
    $off = User::factory()->create();
    $this->actingAs($off);
    expect(TwoFactorNotice::canView())->toBeTrue();

    $off->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    expect(TwoFactorNotice::isEnabled())->toBeTrue()
        ->and(TwoFactorNotice::canView())->toBeFalse();

    // Per operator, not per store: a second one who has not set it up is
    // still told.
    $this->actingAs(User::factory()->create());
    expect(TwoFactorNotice::canView())->toBeTrue();
});

it('never stores the secret in a readable form', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['alpha-one', 'beta-two']);

    $raw = DB::table('users')->where('id', $user->id)->first();

    // Encrypted rather than hashed, because a code has to be checked against
    // it — but a dump of this table must not hand over the ability to pass
    // the challenge it exists to protect.
    expect($raw->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($raw->app_authentication_recovery_codes)->not->toContain('alpha-one')
        // And it still reads back, or the challenge could never be answered.
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['alpha-one', 'beta-two']);
});

it('keeps the secret out of anything serialised', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect(json_encode($user->fresh()))->not->toContain('app_authentication_secret');
});
