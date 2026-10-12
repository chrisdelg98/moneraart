<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tells the browser what it may and may not do', function (): void {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    // A signed download path in a referrer is the path leaking.
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('names every source the storefront actually loads', function (): void {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        // Nothing may frame us: a clickjacking overlay on the add-to-cart
        // button needs exactly this to be missing.
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        // Checkout posts here and to PayPal, nowhere else. Without it an
        // injected form could post a customer's address somewhere we do not own.
        ->toContain("form-action 'self' https://www.paypal.com")
        ->toContain('https://www.paypal.com');
});

it('allows our own inline scripts by nonce, not by opening the door', function (): void {
    $response = $this->get(route('shop'));

    $csp = $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $csp, $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        // The shop's filter script carries the same nonce the header names.
        ->and($response->getContent())->toContain('nonce="'.$matches[1].'"')
        ->and($csp)->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('gives each request a nonce of its own', function (): void {
    $first = $this->get('/')->headers->get('Content-Security-Policy');
    $second = $this->get('/')->headers->get('Content-Security-Policy');

    // A nonce reused across responses is a nonce an attacker can learn.
    expect($first)->not->toBe($second);
});

it('leaves the admin out of the content policy, but not out of the rest', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->get('/admin');

    // Filament leans on inline handlers and eval; a policy strict enough to
    // be worth having would stop the panel working.
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();

    $response->assertHeader('X-Frame-Options', 'DENY');
});

it('only promises HSTS where HTTPS is actually in use', function (): void {
    // Announcing it over plain HTTP tells a browser to refuse the site.
    expect($this->get('/')->headers->get('Strict-Transport-Security'))->toBeNull();
});
