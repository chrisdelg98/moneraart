<?php

declare(strict_types=1);

use App\Actions\Downloads\IssueDownloadGrants;
use App\Actions\Orders\CompleteOrder;
use App\Enums\OrderStatus;
use App\Models\DownloadGrant;
use App\Models\DownloadLog;
use App\Notifications\DownloadLinksReady;
use App\Services\Cart\CartService;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->seed(AttributeSeeder::class);
    $this->cart = app(CartService::class);
});

it('issues one grant per purchased file and emails them', function (): void {
    Notification::fake();
    $order = paidOrder();

    app(CompleteOrder::class)($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(DownloadGrant::count())->toBe(1);

    Notification::assertSentTo($order->customer, DownloadLinksReady::class);
});

it('stores only the hash, never the token itself', function (): void {
    Notification::fake();
    $order = paidOrder();

    $grants = app(IssueDownloadGrants::class)($order);
    $grant = $grants->first();
    $plain = $grant->plainToken;

    // A database dump must yield no usable links. See §8.2.
    expect($plain)->toMatch('/^[0-9a-f]{64}$/')
        ->and($grant->token_hash)->toBe(hash('sha256', $plain))
        ->and(DownloadGrant::where('token_hash', $plain)->exists())->toBeFalse();
});

it('serves the file and counts the download', function (): void {
    Notification::fake();
    $order = paidOrder();
    $grant = app(IssueDownloadGrants::class)($order)->first();

    $this->get(route('download', $grant->plainToken))->assertOk();

    expect($grant->fresh()->download_count)->toBe(1)
        ->and($grant->fresh()->first_downloaded_at)->not->toBeNull()
        ->and(DownloadLog::where('status', 'ok')->count())->toBe(1);
});

it('refuses once the download limit is spent', function (): void {
    Notification::fake();
    $order = paidOrder();
    $grant = app(IssueDownloadGrants::class)($order)->first();
    $token = $grant->plainToken;

    foreach (range(1, 5) as $i) {
        $this->get(route('download', $token))->assertOk();
    }

    $this->get(route('download', $token))
        ->assertStatus(429)
        ->assertSee('used up');

    expect($grant->fresh()->download_count)->toBe(5);
});

it('refuses an expired link', function (): void {
    Notification::fake();
    $grant = app(IssueDownloadGrants::class)(paidOrder())->first();
    $grant->forceFill(['expires_at' => now()->subHour()])->save();

    $this->get(route('download', $grant->plainToken))
        ->assertStatus(410)
        ->assertSee('expired');
});

it('refuses a revoked link', function (): void {
    Notification::fake();
    $grant = app(IssueDownloadGrants::class)(paidOrder())->first();
    $grant->forceFill(['revoked_at' => now()])->save();

    $this->get(route('download', $grant->plainToken))->assertStatus(410);
});

it('404s an unknown token', function (): void {
    $this->get(route('download', str_repeat('a', 64)))->assertNotFound();

    expect(DownloadLog::count())->toBe(0);
});

it('logs every attempt, including the refused ones', function (): void {
    Notification::fake();
    $grant = app(IssueDownloadGrants::class)(paidOrder())->first();
    $token = $grant->plainToken;

    $this->get(route('download', $token));
    $grant->forceFill(['revoked_at' => now()])->save();
    $this->get(route('download', $token));

    // This log is the evidence that wins a payment dispute.
    expect(DownloadLog::pluck('status')->all())->toBe(['ok', 'revoked'])
        ->and(DownloadLog::first()->ip_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('offers renewal on an expired link but not on a revoked one', function (): void {
    Notification::fake();
    $grant = app(IssueDownloadGrants::class)(paidOrder())->first();
    $token = $grant->plainToken;

    $grant->forceFill(['expires_at' => now()->subHour()])->save();
    $this->get(route('download', $token))->assertSee('Email me a new link');

    $grant->forceFill(['expires_at' => now()->addDay(), 'revoked_at' => now()])->save();
    $this->get(route('download', $token))->assertDontSee('Email me a new link');
});

it('emails fresh links to the buyer, never shows them to whoever asked', function (): void {
    Notification::fake();
    $order = paidOrder();
    app(CompleteOrder::class)($order);

    $grant = DownloadGrant::firstOrFail();
    $grant->forceFill(['expires_at' => now()->subHour()])->save();

    // Safe without authentication precisely because the result goes to the
    // original inbox. See §8.4.
    $this->post(route('download.renew', $grant->uuid))
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentToTimes($order->customer, DownloadLinksReady::class, 2);

    expect(DownloadGrant::where('id', $grant->id)->exists())->toBeFalse()
        ->and(DownloadGrant::count())->toBe(1);
});

it('refuses renewal for a refunded order', function (): void {
    Notification::fake();
    $order = paidOrder();
    $grant = app(IssueDownloadGrants::class)($order)->first();

    $order->forceFill(['status' => OrderStatus::Refunded])->save();

    $this->post(route('download.renew', $grant->uuid))->assertSessionHas('error');
});

it('issues grants once, even if completion is attempted twice', function (): void {
    Notification::fake();
    $order = paidOrder();

    app(CompleteOrder::class)($order);
    app(CompleteOrder::class)($order->fresh());

    expect(DownloadGrant::count())->toBe(1);
});

it('gives a free order a shorter link life', function (): void {
    Notification::fake();
    $order = paidOrder();
    $order->forceFill(['is_free' => true])->save();

    $grant = app(IssueDownloadGrants::class)($order->fresh())->first();

    expect($grant->expires_at->diffInHours(now()->addHours(24), absolute: true))->toBeLessThan(1);
});
