<?php

declare(strict_types=1);

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    $this->actingAs(User::factory()->create());
    $this->style = Attribute::where('key', 'style')->firstOrFail();
});

it('renders the categories screen', function (): void {
    $this->get('/admin/categories')->assertOk();
});

it('offers only the axes a person fills in', function (): void {
    $computed = Attribute::whereIn('key', Attribute::COMPUTED)->pluck('id');

    // Ratio, orientation and colour are read off the uploaded files. A
    // hand-edited copy can disagree with the artwork it describes.
    expect(CategoryResource::getEloquentQuery()->whereIn('attribute_id', $computed)->count())
        ->toBe(0)
        ->and(CategoryResource::getEloquentQuery()->count())->toBeGreaterThan(70);
});

it('creates a category with both labels', function (): void {
    livewire(CreateCategory::class)
        ->fillForm([
            'attribute_id' => $this->style->id,
            'label_en' => 'Risograph',
            'label_es' => 'Risografía',
            'value' => 'risograph',
        ])
        ->callAction('create')
        ->assertHasNoFormErrors();

    $value = AttributeValue::where('value', 'risograph')->firstOrFail();

    expect($value->label('en'))->toBe('Risograph')
        ->and($value->label('es'))->toBe('Risografía');
});

it('falls back to the English name when no Spanish is given', function (): void {
    livewire(CreateCategory::class)
        ->fillForm([
            'attribute_id' => $this->style->id,
            'label_en' => 'Brutalist',
            'value' => 'brutalist',
        ])
        ->callAction('create')
        ->assertHasNoFormErrors();

    // A blank filter label in the shop is worse than an untranslated one.
    expect(AttributeValue::where('value', 'brutalist')->firstOrFail()->label('es'))
        ->toBe('Brutalist');
});

it('refuses a URL name already taken', function (): void {
    livewire(CreateCategory::class)
        ->fillForm([
            'attribute_id' => $this->style->id,
            'label_en' => 'Another Minimal',
            'value' => 'minimalist',
        ])
        ->callAction('create')
        ->assertHasFormErrors(['value']);
});

it('freezes the URL name once links exist', function (): void {
    $value = AttributeValue::where('value', 'minimalist')->firstOrFail();

    // ?style=minimalist is in every link already shared.
    livewire(EditCategory::class, ['record' => $value->getRouteKey()])
        ->assertFormFieldIsDisabled('value')
        ->assertFormFieldIsDisabled('attribute_id');
});

it('renames a category without touching its URL', function (): void {
    $value = AttributeValue::where('value', 'retro')->firstOrFail();

    livewire(EditCategory::class, ['record' => $value->getRouteKey()])
        ->fillForm(['label_en' => 'Retro Revival'])
        ->callAction('save')
        ->assertHasNoFormErrors();

    expect($value->refresh()->label('en'))->toBe('Retro Revival')
        ->and($value->value)->toBe('retro');
});

it('refuses to delete a category artwork is filed under', function (): void {
    $value = AttributeValue::where('value', 'minimalist')->firstOrFail();
    Product::factory()->create()->attributeValues()->attach($value);

    livewire(ListCategories::class)
        ->callTableAction('delete', $value);

    // Deleting detaches it from every product silently — a catalogue edit
    // disguised as a tidy-up.
    expect(AttributeValue::whereKey($value->id)->exists())->toBeTrue();
});
