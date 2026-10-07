<?php

declare(strict_types=1);

use App\Models\LegalDocument;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(LegalDocumentSeeder::class));

it('serves every legal page', function (string $slug, string $expects): void {
    $this->get("/{$slug}")->assertOk()->assertSee($expects);
})->with([
    ['terms', 'Terms of Sale'],
    ['privacy', 'Privacy Policy'],
    ['refunds', 'Refunds'],
    ['how-we-work', 'How We Make Our Art'],
]);

it('states which version is in force', function (): void {
    // In a dispute the question is what this buyer agreed to and when.
    $this->get('/terms')->assertOk()->assertSee('Version 1.0');
});

it('carries the clause that makes the no-refund policy hold', function (): void {
    // EU and UK buyers can only waive the 14-day withdrawal right by expressly
    // consenting to immediate delivery. See §7.7.3.
    $this->get('/terms')->assertOk()
        ->assertSee('all sales are final')
        ->assertSee('cannot be returned or cancelled once delivered');
});

it('discloses that the artwork is AI-generated', function (): void {
    $this->get('/terms')->assertOk()->assertSee('AI image-generation tools');
});

it('links the consent box at checkout to real documents', function (): void {
    // The whole point: asking someone to accept a document that does not
    // exist is not consent.
    $html = $this->get('/terms')->assertOk()->getContent();

    expect($html)->not->toContain('href="#"');
    expect(route('legal', 'terms'))->toEndWith('/terms');
});

it('404s a page with no published version', function (): void {
    LegalDocument::where('key', 'terms')->firstOrFail()
        ->versions()->update(['published_at' => null]);

    $this->get('/terms')->assertNotFound();
});

it('404s an unknown legal slug', function (): void {
    $this->get('/not-a-policy')->assertNotFound();
});

it('claims no security measure that is not actually in place', function (): void {
    // Two-factor authentication is Phase 8. Claiming it in a privacy policy
    // before it exists is a material misstatement.
    $this->get('/privacy')->assertOk()
        ->assertDontSee('two-factor')
        ->assertSee('recorded with who did it and when');
});

it('promises no retention schedule that nothing enforces', function (): void {
    // Cleanup jobs do not exist yet, so a precise schedule would be a promise
    // rather than a policy.
    $this->get('/privacy')->assertOk()
        ->assertDontSee('7 years for accounting')
        ->assertSee('as long as accounting and tax rules require');
});

it('admits that a held payment is not immediate', function (): void {
    // The customer who meets manual review is the one who most needs to have
    // been told it can happen.
    $this->get('/terms')->assertOk()
        ->assertSee('holds a payment for review');
});

it('frames the charge as preparation and access, not as selling an image', function (): void {
    // This is what holds the licence model together: if the sale is
    // preparation and access, the unsettled copyright status of an
    // AI-generated image stops being the centre of the deal. See §2.5.
    $this->get('/terms')->assertOk()
        ->assertSee('What you are paying for')
        ->assertSee('Not the image as an object')
        ->assertSee('storage and delivery');
});
