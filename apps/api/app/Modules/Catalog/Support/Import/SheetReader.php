<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support\Import;

use App\Support\Exceptions\DomainRuleException;
use DateTimeInterface;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads the first sheet of an .xlsx or .csv file into rows keyed by column key.
 */
final class SheetReader
{
    public const MAX_ROWS = 5000;

    /**
     * @return list<array{row: int, cells: array<string, string>}> row = the line number in the sheet
     */
    public function read(string $path, string $extension): array
    {
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        try {
            $map = null;
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    $values = array_map($this->text(...), $row->toArray());

                    if ($map === null) {
                        if (implode('', $values) === '') {
                            continue; // blank lines above the header
                        }
                        $map = ImportColumns::mapHeader($values);
                        if ($missing = ImportColumns::missingRequired($map)) {
                            throw new DomainRuleException(
                                'الشيت ناقصه أعمدة: '.implode('، ', $missing).'. نزّل النموذج واستخدم نفس العناوين.',
                                'import_missing_columns',
                                context: ['missing' => $missing],
                            );
                        }

                        continue;
                    }

                    $cells = [];
                    foreach ($map as $index => $key) {
                        $cells[$key] = $values[$index] ?? '';
                    }

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    if (count($rows) === self::MAX_ROWS) {
                        throw new DomainRuleException('الملف فيه أكتر من '.self::MAX_ROWS.' صف. قسّمه على أكتر من ملف.', 'import_too_many_rows');
                    }

                    $rows[] = ['row' => (int) $line, 'cells' => $cells];
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        if ($map === null || $rows === []) {
            throw new DomainRuleException('الملف فاضي أو مفيهوش صفوف تحت العناوين.', 'import_empty');
        }

        return $rows;
    }

    private function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
            is_bool($value) => $value ? '1' : '0',
            default => trim((string) $value),
        };
    }
}
