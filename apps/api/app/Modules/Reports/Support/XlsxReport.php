<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * A report as an .xlsx sheet (right-to-left): title, period, headline figures, then the table.
 * Money is written in pounds as numbers, so Excel can sum it.
 */
final class XlsxReport
{
    public function write(string $path, Report $report, ReportQuery $query, ReportResult $result, string $shopName): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($report->title(), 0, 31));
        $sheet->setSheetView((new SheetView)->setRightToLeft(true));
        $sheet->setColumnWidth(30, 1);
        $sheet->setColumnWidthForRange(16, 2, max(2, count($result->columns)));

        $title = (new Style)->setFontBold()->setFontSize(14);
        $bold = (new Style)->setFontBold();
        $header = (new Style)->setFontBold()->setBackgroundColor('E3F4F1');
        $money = (new Style)->setFormat('#,##0.00');
        $moneyBold = (new Style)->setFormat('#,##0.00')->setFontBold();

        $writer->addRow(Row::fromValues(["{$shopName} — {$report->title()}"], $title));
        $scope = [];
        if ($report->usesDates()) {
            $scope[] = "من {$query->from->toDateString()} لـ {$query->to->toDateString()}";
        }
        if ($report->usesBranches()) {
            $scope[] = 'الفروع: '.implode('، ', $query->branches);
        }
        if ($scope !== []) {
            $writer->addRow(Row::fromValues([implode(' · ', $scope)]));
        }
        foreach ($result->summary as $item) {
            $writer->addRow(new Row([
                Cell::fromValue($item['label'], $bold),
                $this->cell($item['value'], $item['type'], $money),
            ]));
        }
        $writer->addRow(Row::fromValues(['']));

        $writer->addRow(Row::fromValues(array_map(fn (Column $c) => $c->label, $result->columns), $header));
        foreach ($result->rows as $row) {
            $writer->addRow(new Row(array_map(fn (Column $c) => $this->cell($row[$c->key] ?? null, $c->type, $money), $result->columns)));
        }
        if ($result->totals !== null) {
            $writer->addRow(new Row(array_map(
                fn (Column $c) => $this->cell($result->totals[$c->key] ?? null, $c->type, $moneyBold, $bold),
                $result->columns,
            )));
        }
        foreach ($result->notes as $note) {
            $writer->addRow(Row::fromValues([$note]));
        }

        $writer->close();
    }

    private function cell(mixed $value, string $type, Style $money, ?Style $text = null): Cell
    {
        if ($value === null || $value === '') {
            return Cell::fromValue('', $text);
        }
        if (is_string($value) && ! is_numeric($value)) {
            return Cell::fromValue(match ($type) {
                'date', 'month' => substr($value, 0, $type === 'month' ? 7 : 10),
                'datetime' => str_replace('T', ' ', substr($value, 0, 16)),
                default => $value,
            }, $text);
        }

        return match ($type) {
            'money' => Cell::fromValue(round((int) $value / 100, 2), $money),
            'percent' => Cell::fromValue((float) $value / 100, (new Style)->setFormat('0.0%')),
            'int' => Cell::fromValue((int) $value, $text),
            default => Cell::fromValue($value, $text),
        };
    }
}
