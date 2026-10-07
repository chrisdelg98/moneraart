<?php

declare(strict_types=1);

use App\Actions\Products\GenerateProductTemplate;
use App\Actions\Products\ImportProductsFromSpreadsheet;
use App\Enums\ProductStatus;
use App\Models\Product;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AttributeSeeder::class);
    $this->import = app(ImportProductsFromSpreadsheet::class);
});

/**
 * Writes a sheet with the template's headers plus the given rows.
 *
 * @param  list<list<string|null>>  $rows
 * @param  list<string>|null  $headers
 */
function sheetWith(array $rows, ?array $headers = null): string
{
    $headers ??= ['Title *', 'Subtitle', 'Description', 'Price *', 'Type',
        'Style', 'Room', 'Theme', 'Title (ES)', 'Subtitle (ES)', 'Description (ES)'];

    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Products');
    $sheet->fromArray($headers, null, 'A1');

    foreach ($rows as $i => $row) {
        $sheet->fromArray($row, null, 'A'.($i + 2));
    }

    $path = sys_get_temp_dir().'/'.uniqid('sheet_', true).'.xlsx';
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return $path;
}

/** Saves a freshly generated template and returns its path. */
function freshTemplate(): string
{
    $path = sys_get_temp_dir().'/'.uniqid('tpl_', true).'.xlsx';
    file_put_contents($path, app(GenerateProductTemplate::class)());

    return $path;
}

it('generates a template that opens as a valid workbook', function (): void {
    $book = IOFactory::load(freshTemplate());

    expect($book->getSheetByName('Products'))->not->toBeNull()
        ->and($book->getSheetByName('Lists'))->not->toBeNull()
        ->and($book->getSheetByName('Products')->getCell('A1')->getValue())->toContain('Title');
});

it('hides the lists sheet so the taxonomy is not mistaken for data', function (): void {
    expect(IOFactory::load(freshTemplate())->getSheetByName('Lists')->getSheetState())->toBe('hidden');
});

it('fills the dropdown lists from the database, not from a hard-coded list', function (): void {
    $values = IOFactory::load(freshTemplate())->getSheetByName('Lists')->toArray();
    $flat = collect($values)->flatten()->filter()->all();

    expect($flat)->toContain('mid-century')->toContain('coffee-bar')->toContain('mini_set');
});

it('imports a filled-in sheet as drafts', function (): void {
    $path = sheetWith([
        ['Coffee Bar Art', 'Warm tones', 'A description.', '5.99', 'single', 'mid-century', 'kitchen, coffee-bar', 'coffee', null, null, null],
        ['Botanical Set', null, null, '12.99', 'mini_set', 'minimalist', 'living-room', 'nature', null, null, null],
    ]);

    $result = ($this->import)($path);

    expect($result->ok)->toBeTrue()
        ->and($result->created)->toBe(2)
        ->and(Product::pluck('status')->unique()->all())->toBe([ProductStatus::Draft]);
});

it('splits a comma-separated room cell into several attributes', function (): void {
    ($this->import)(sheetWith([
        ['Multi Room', null, null, '5.99', 'single', null, 'kitchen, coffee-bar, office', null, null, null, null],
    ]));

    expect(Product::firstOrFail()->valuesFor('room')->pluck('value')->sort()->values()->all())
        ->toBe(['coffee-bar', 'kitchen', 'office']);
});

it('reads the Spanish columns into a second translation', function (): void {
    ($this->import)(sheetWith([
        ['Coffee Bar', null, null, '5.99', 'single', null, null, null,
            'Bar de Cafe', 'Tonos calidos', 'Una descripcion.'],
    ]));

    $product = Product::firstOrFail();

    expect($product->title('en'))->toBe('Coffee Bar')
        ->and($product->title('es'))->toBe('Bar de Cafe')
        ->and($product->translate('es')->subtitle)->toBe('Tonos calidos');
});

it('skips the worked example row that ships in the template', function (): void {
    $result = ($this->import)(sheetWith([
        [GenerateProductTemplate::EXAMPLE_TITLE, 'x', 'x', '5.99', 'single', null, null, null, null, null, null],
        ['Real Product', null, null, '5.99', 'single', null, null, null, null, null, null],
    ]));

    expect($result->created)->toBe(1)
        ->and(Product::firstOrFail()->title('en'))->toBe('Real Product');
});

it('ignores blank rows below the data', function (): void {
    $result = ($this->import)(sheetWith([
        ['Only Row', null, null, '5.99', 'single', null, null, null, null, null, null],
        [null, null, null, null, null, null, null, null, null, null, null],
        [null, null, null, null, null, null, null, null, null, null, null],
    ]));

    expect($result->ok)->toBeTrue()->and($result->created)->toBe(1);
});

it('finds columns by header, not by position', function (): void {
    // Price moved to the front and a column removed — values must not shift.
    $path = sheetWith(
        [['9.99', 'Reordered Product', 'single']],
        ['Price *', 'Title *', 'Type'],
    );

    ($this->import)($path);

    $product = Product::firstOrFail();

    expect($product->title('en'))->toBe('Reordered Product')
        ->and($product->price_cents)->toBe(999);
});

it('explains itself when the required columns are missing', function (): void {
    $result = ($this->import)(sheetWith([['something']], ['Random Column']));

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0])->toContain('Title')->toContain('Price');
});

it('rejects a file that is not a spreadsheet', function (): void {
    $path = sys_get_temp_dir().'/'.uniqid('bad_', true).'.xlsx';
    file_put_contents($path, 'this is not a workbook');

    expect(($this->import)($path)->ok)->toBeFalse();
});

it('applies the same price floor as the JSON route', function (): void {
    $result = ($this->import)(sheetWith([
        ['Fine', null, null, '5.99', 'single', null, null, null, null, null, null],
        ['Too Cheap', null, null, '0.50', 'single', null, null, null, null, null, null],
    ]));

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0])->toContain('below the 1.99 minimum')
        ->and(Product::count())->toBe(0);
});

it('rejects an unknown attribute value, as the JSON route does', function (): void {
    $result = ($this->import)(sheetWith([
        ['Typo', null, null, '5.99', 'single', 'mid-centry', null, null, null, null, null],
    ]));

    expect($result->errors[0])->toContain('unknown style');
});

it('catches a name repeated within the same file', function (): void {
    $result = ($this->import)(sheetWith([
        ['Same Name', null, null, '5.99', 'single', null, null, null, null, null, null],
        ['Same Name', null, null, '6.99', 'single', null, null, null, null, null, null],
    ]));

    expect($result->ok)->toBeFalse()
        ->and($result->errors[0])->toContain('more than once')
        ->and(Product::count())->toBe(0);
});

it('round-trips: a downloaded template fills in and imports', function (): void {
    $path = freshTemplate();

    $book = IOFactory::load($path);
    $sheet = $book->getSheetByName('Products');

    // Fill the first real row, exactly as the administrator would.
    $sheet->setCellValue('A3', 'Filled From Template');
    $sheet->setCellValue('D3', '7.99');
    $sheet->setCellValue('E3', 'single');
    $sheet->setCellValue('F3', 'retro');
    $sheet->setCellValue('G3', 'bar-cart');

    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $result = ($this->import)($path);

    expect($result->ok)->toBeTrue()
        ->and($result->created)->toBe(1)
        ->and(Product::firstOrFail()->title('en'))->toBe('Filled From Template')
        ->and(Product::firstOrFail()->valuesFor('style')->pluck('value')->all())->toBe(['retro']);
});
