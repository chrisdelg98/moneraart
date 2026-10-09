<?php

declare(strict_types=1);

use App\Actions\Products\StoreProductFile;
use App\Filament\Pages\StorageSettings;
use App\Models\Product;
use App\Models\User;
use App\Services\Storage\StorageHealthCheck;
use App\Services\Storage\StorageManager;
use App\Support\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

/** The four values R2 needs, so hasCredentials() stops short-circuiting. */
function r2Credentials(): void
{
    Settings::setMany([
        'storage.r2.account_id' => 'acc123',
        'storage.r2.access_key_id' => 'key123',
        'storage.r2.secret_access_key' => 'secret123',
        'storage.r2.bucket' => 'monera',
    ]);
}

it('renders the storage page', function (): void {
    $this->get('/admin/storage-settings')->assertOk();
});

it('writes new uploads to the disk the setting names', function (): void {
    Storage::fake('r2');
    r2Credentials();
    Settings::set('storage.driver', 'r2');

    $file = app(StoreProductFile::class)(Product::factory()->create(), artwork(800, 800), 'art.jpg');

    // The disk is recorded on the row, which is what makes switching safe.
    expect($file->disk)->toBe('r2');
    Storage::disk('r2')->assertExists($file->path);
});

it('leaves files already sold on the disk they were written to', function (): void {
    Storage::fake('r2');
    $product = Product::factory()->create();

    $before = app(StoreProductFile::class)($product, artwork(800, 800), 'first.jpg');

    r2Credentials();
    Settings::set('storage.driver', 'r2');

    $after = app(StoreProductFile::class)($product, artwork(600, 600), 'second.jpg');

    // Nothing moved, and nothing had to: a customer who bought the first one
    // still downloads it from where it is.
    expect($before->refresh()->disk)->toBe('private')
        ->and($after->disk)->toBe('r2');
});

it('falls back to the local disk when the setting is nonsense', function (): void {
    Settings::set('storage.driver', 'dropbox');

    expect(app(StorageManager::class)->driver())->toBe('local')
        ->and(app(StorageManager::class)->diskName())->toBe('private');
});

it('reports local storage as writable', function (): void {
    $checks = app(StorageHealthCheck::class)->run();

    expect($checks[0]['ok'])->toBeTrue()
        ->and($checks[0]['label'])->toContain('writable');
});

it('refuses R2 before all four credentials are given', function (): void {
    Settings::setMany(['storage.driver' => 'r2', 'storage.r2.bucket' => 'monera']);

    $checks = app(StorageHealthCheck::class)->run();

    expect($checks[0]['ok'])->toBeFalse()
        ->and($checks[0]['label'])->toContain('Credentials');
});

it('refuses to save a driver whose storage does not work', function (): void {
    r2Credentials();

    livewire(StorageSettings::class)
        ->fillForm([
            'driver' => 'r2',
            'account_id' => 'acc123',
            'bucket' => 'monera',
            'access_key_id' => 'key123',
        ])
        ->callAction('save');

    // Saving a driver that does not work means the next upload is lost
    // quietly, which is the one outcome this page exists to prevent.
    expect(app(StorageManager::class)->driver())->toBe('local');
});

it('calls a publicly readable bucket what it is', function (): void {
    Storage::fake('r2');
    r2Credentials();
    Settings::set('storage.driver', 'r2');

    // Both the signed and the unsigned request succeed: the object is public.
    Http::fake(['*' => Http::response('probe', 200)]);

    $checks = collect(app(StorageHealthCheck::class)->run());
    $public = $checks->firstWhere('label', 'The bucket is not public');

    // A public bucket makes the counter, the expiry and revocation all
    // decorative — the file needs no token at all. See §8.6.2.
    expect($public['ok'])->toBeFalse()
        ->and($public['detail'])->toContain('without a signature');
});

it('runs every check, so none is skipped by an earlier failure', function (): void {
    Storage::fake('r2');
    r2Credentials();
    Settings::set('storage.driver', 'r2');
    Http::fake(['*' => Http::response('probe', 200)]);

    $labels = collect(app(StorageHealthCheck::class)->run())->pluck('label');

    // If a check is missing, the assertions above are passing on absence.
    expect($labels)->toContain('Credentials are complete')
        ->toContain('The bucket accepts uploads')
        ->toContain('Signed links work')
        ->toContain('The bucket is not public');
});
