<?php

declare(strict_types=1);

use App\Actions\Products\ImportProductsFromJson;
use App\Enums\ProductStatus;
use App\Filament\Pages\ImportProducts;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    $this->actingAs(User::factory()->create());
});

it('renders the import page', function (): void {
    $this->get(ImportProducts::getUrl())->assertOk();
});

it('imports pasted json as drafts', function (): void {
    livewire(ImportProducts::class)
        ->fillForm(['payload' => ImportProductsFromJson::sample()])
        ->call('importJson');

    expect(Product::count())->toBe(2)
        ->and(Product::pluck('status')->unique()->all())->toBe([ProductStatus::Draft]);
});

it('clears the box after a successful import', function (): void {
    livewire(ImportProducts::class)
        ->fillForm(['payload' => ImportProductsFromJson::sample()])
        ->call('importJson')
        ->assertFormSet(['payload' => '']);
});

it('keeps the payload when the import fails, so it can be fixed', function (): void {
    $bad = json_encode([['title' => 'No Price']]);

    livewire(ImportProducts::class)
        ->fillForm(['payload' => $bad])
        ->call('importJson')
        ->assertFormSet(['payload' => $bad]);

    expect(Product::count())->toBe(0);
});

it('does nothing with an empty box', function (): void {
    livewire(ImportProducts::class)
        ->fillForm(['payload' => '   '])
        ->call('importJson');

    expect(Product::count())->toBe(0);
});

it('loads the example into the box', function (): void {
    livewire(ImportProducts::class)
        ->call('loadSample')
        ->assertFormSet(fn (array $state): bool => str_contains((string) $state['payload'], 'Mid-Century'));
});
