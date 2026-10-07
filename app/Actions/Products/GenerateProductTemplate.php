<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Support\Money;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the .xlsx the administrator fills in.
 *
 * The taxonomy columns carry real dropdowns, populated from the database at
 * download time. That is the whole reason this is a spreadsheet rather than a
 * CSV: a dropdown makes the "unknown style" error almost impossible to cause,
 * where a free-text column makes it almost inevitable.
 */
final class GenerateProductTemplate
{
    /** Column letter => [header, width, note]. */
    private const COLUMNS = [
        'A' => ['Title *', 42, 'Required. The URL is generated from this.'],
        'B' => ['Subtitle', 32, 'One editorial line for listing cards.'],
        'C' => ['Description', 52, 'Required before the product can be published.'],
        'D' => ['Price *', 10, 'Required. Written as 5.99, not in cents.'],
        'E' => ['Type', 14, 'single, mini_set, collection or bundle.'],
        'F' => ['Style', 20, 'Pick from the list.'],
        'G' => ['Room', 26, 'Pick from the list. Several, separated by commas.'],
        'H' => ['Theme', 20, 'Pick from the list.'],
        'I' => ['Title (ES)', 42, 'Leave empty to add the Spanish version later.'],
        'J' => ['Subtitle (ES)', 32, ''],
        'K' => ['Description (ES)', 52, ''],
    ];

    private const FIRST_DATA_ROW = 3;

    private const LAST_VALIDATED_ROW = 500;

    public function __invoke(): string
    {
        $book = new Spreadsheet;
        $book->getProperties()
            ->setTitle('Monera Art — product import')
            ->setCreator('Monera Art');

        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Products');

        $this->writeHeader($sheet);
        $this->writeExampleRow($sheet);

        $lists = $this->writeLists($book);
        $this->applyDropdowns($sheet, $lists);

        $sheet->freezePane('A'.self::FIRST_DATA_ROW);
        $sheet->setSelectedCell('A'.self::FIRST_DATA_ROW);
        $book->setActiveSheetIndex(0);

        return $this->toString($book);
    }

    private function writeHeader(Worksheet $sheet): void
    {
        foreach (self::COLUMNS as $column => [$label, $width, $note]) {
            $sheet->setCellValue("{$column}1", $label);
            $sheet->getColumnDimension($column)->setWidth($width);

            if ($note !== '') {
                // A comment on the header beats a separate instructions sheet
                // nobody opens.
                $sheet->getComment("{$column}1")->getText()->createTextRun($note);
            }
        }

        $lastColumn = array_key_last(self::COLUMNS);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16140F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(26);
    }

    /**
     * Row 2 is a worked example, greyed and italic so it reads as a sample.
     * The importer skips it by title, so leaving it in is harmless.
     */
    private function writeExampleRow(Worksheet $sheet): void
    {
        $values = [
            'A' => self::EXAMPLE_TITLE,
            'B' => 'Warm retro tones for a kitchen nook',
            'C' => 'A set of warm, muted prints for a coffee corner.',
            'D' => '5.99',
            'E' => 'single',
            'F' => 'mid-century',
            'G' => 'kitchen, coffee-bar',
            'H' => 'coffee',
            'I' => 'Arte mural de bar de café mid-century',
            'J' => 'Tonos cálidos retro para un rincón de cocina',
            'K' => 'Un conjunto de láminas cálidas para un rincón de café.',
        ];

        foreach ($values as $column => $value) {
            $sheet->setCellValueExplicit(
                "{$column}2",
                $value,
                DataType::TYPE_STRING,
            );
        }

        $lastColumn = array_key_last(self::COLUMNS);

        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9A9387']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2DDD2']]],
        ]);
    }

    public const EXAMPLE_TITLE = 'EXAMPLE — delete this row';

    /**
     * Allowed values live on a hidden sheet, which is how Excel wants its
     * dropdown sources.
     *
     * @return array<string, string> attribute key => range reference
     */
    private function writeLists(Spreadsheet $book): array
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Lists');

        $ranges = [];
        $column = 'A';

        $sources = ['type' => array_map(fn (ProductType $t): string => $t->value, ProductType::cases())];

        foreach (Attribute::with('values')->whereIn('key', Attribute::MANUAL)->get() as $attribute) {
            $sources[$attribute->key] = $attribute->values->pluck('value')->all();
        }

        foreach ($sources as $key => $values) {
            $sheet->setCellValue("{$column}1", $key);
            $row = 2;

            foreach ($values as $value) {
                $sheet->setCellValue("{$column}{$row}", $value);
                $row++;
            }

            $ranges[$key] = sprintf('Lists!$%s$2:$%s$%d', $column, $column, max($row - 1, 2));
            // ++ on a string is deprecated as of PHP 8.3.
            $column = str_increment($column);
        }

        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return $ranges;
    }

    /** @param array<string, string> $lists */
    private function applyDropdowns(Worksheet $sheet, array $lists): void
    {
        $columns = ['E' => 'type', 'F' => 'style', 'H' => 'theme'];

        foreach ($columns as $column => $key) {
            if (! isset($lists[$key])) {
                continue;
            }

            for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_VALIDATED_ROW; $row++) {
                $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank(true)
                    ->setShowDropDown(true)
                    ->setShowErrorMessage(true)
                    ->setErrorTitle('Not a known value')
                    ->setError('Pick one from the list.')
                    ->setFormula1($lists[$key]);
            }
        }

        // Room takes several values, so a single-select dropdown would be
        // wrong. A note carries the allowed values instead.
        $roomValues = Attribute::with('values')->where('key', 'room')->first()?->values->pluck('value')->implode(', ');

        if ($roomValues) {
            $sheet->getComment('G1')->getText()->createTextRun(
                "Separate several with commas.\n\nAllowed: {$roomValues}"
            );
        }

        $floor = Money::fromCents((int) config('store.pricing.minimum_price_cents'))->toDecimalString();
        $sheet->getComment('D1')->getText()->createTextRun(
            "Required. Written as 5.99, not in cents.\n\nMinimum {$floor}, or 0 for a free product."
        );
    }

    private function toString(Spreadsheet $book): string
    {
        $writer = new Xlsx($book);
        $writer->setIncludeCharts(false);

        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();

        $book->disconnectWorksheets();

        return $contents;
    }

    public static function filename(): string
    {
        return 'monera-art-products-'.now()->format('Y-m-d').'.xlsx';
    }
}
