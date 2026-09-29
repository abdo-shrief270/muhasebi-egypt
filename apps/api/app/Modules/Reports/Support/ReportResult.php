<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

/**
 * Every report has the same shape, so one screen, one Excel export and one print layout serve
 * them all: headline figures, a table (with an optional totals row) and an optional chart.
 */
final readonly class ReportResult
{
    /**
     * @param  list<Column>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{label: string, value: int|float|string|null, type: string, hint?: string}>  $summary
     * @param  array<string, mixed>|null  $totals  a row keyed like $rows
     * @param  array{kind: 'daily'|'bars', label: string, type: string, points: list<array{label: string, value: int|float, date?: string}>}|null  $chart
     * @param  list<string>  $notes
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public array $summary = [],
        public ?array $totals = null,
        public ?array $chart = null,
        public array $notes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'columns' => array_map(fn (Column $c) => $c->toArray(), $this->columns),
            'rows' => $this->rows,
            'totals' => $this->totals,
            'chart' => $this->chart,
            'notes' => $this->notes,
        ];
    }
}
