<?php

declare(strict_types=1);

namespace App\Actions\Products;

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/**
 * Reads the filled-in template and hands it to the shared importer.
 *
 * Columns are located by their header text rather than by position, so a column
 * the administrator moved or an extra one they added does not silently shift
 * every value one field to the left.
 */
final readonly class ImportProductsFromSpreadsheet
{
    /** Header text (lowercased, trimmed of markers) => field name. */
    private const HEADERS = [
        'title' => 'title',
        'subtitle' => 'subtitle',
        'description' => 'description',
        'price' => 'price',
        'type' => 'type',
        'style' => 'style',
        'room' => 'room',
        'theme' => 'theme',
        'title (es)' => 'title_es',
        'subtitle (es)' => 'subtitle_es',
        'description (es)' => 'description_es',
    ];

    public function __construct(private ProductImporter $importer) {}

    public function __invoke(string $path, string $locale = 'en'): ImportResult
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (ReaderException|SpreadsheetException $e) {
            return ImportResult::failed(['That file could not be read: '.$e->getMessage()]);
        }

        $sheet = $book->getSheetByName('Products') ?? $book->getSheet(0);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $sheet->toArray(null, true, false, true);
        $book->disconnectWorksheets();

        if ($rows === []) {
            return ImportResult::failed(['That spreadsheet is empty.']);
        }

        $map = $this->mapHeaders(array_shift($rows));

        if (! isset($map['title'], $map['price'])) {
            return ImportResult::failed([
                'Could not find the "Title" and "Price" columns. Download a fresh template and fill that in.',
            ]);
        }

        $items = [];

        foreach ($rows as $row) {
            $item = $this->readRow($row, $map);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        if ($items === []) {
            return ImportResult::failed(['No filled-in rows were found below the header.']);
        }

        return $this->importer->import($items, $locale);
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array<string, string> field => column letter
     */
    private function mapHeaders(array $header): array
    {
        $map = [];

        foreach ($header as $column => $label) {
            // "Title *" and "Title" are the same column.
            $key = mb_strtolower(trim(str_replace('*', '', (string) $label)));

            if (isset(self::HEADERS[$key])) {
                $map[self::HEADERS[$key]] = (string) $column;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $map
     * @return array<string, mixed>|null
     */
    private function readRow(array $row, array $map): ?array
    {
        $get = function (string $field) use ($row, $map): ?string {
            $column = $map[$field] ?? null;

            if ($column === null) {
                return null;
            }

            $value = trim((string) ($row[$column] ?? ''));

            return $value === '' ? null : $value;
        };

        $title = $get('title');

        // Blank rows below the data are normal in a spreadsheet, not an error.
        if ($title === null) {
            return null;
        }

        // The worked example ships in the template; skipping it means a careless
        // import does not create a product called "EXAMPLE — delete this row".
        if ($title === GenerateProductTemplate::EXAMPLE_TITLE) {
            return null;
        }

        $item = [
            'title' => $title,
            'subtitle' => $get('subtitle'),
            'description' => $get('description'),
            'price' => $get('price'),
            'type' => $get('type') ?? 'single',
            'style' => $get('style'),
            'room' => $get('room'),
            'theme' => $get('theme'),
        ];

        $spanishTitle = $get('title_es');

        if ($spanishTitle !== null || $get('subtitle_es') !== null || $get('description_es') !== null) {
            $item['translations'] = ['es' => [
                'title' => $spanishTitle,
                'subtitle' => $get('subtitle_es'),
                'description' => $get('description_es'),
            ]];
        }

        return $item;
    }
}
