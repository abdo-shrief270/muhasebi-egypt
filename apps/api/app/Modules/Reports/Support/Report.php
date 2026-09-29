<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

interface Report
{
    public function key(): string;

    public function title(): string;

    public function description(): string;

    /** sales | stock | money */
    public function group(): string;

    /** Needed on top of reports.view, if any. */
    public function permission(): ?string;

    public function usesDates(): bool;

    public function usesBranches(): bool;

    /**
     * @return list<array{key: string, label: string, choices: list<array{value: string, label: string}>}>
     */
    public function options(): array;

    public function run(ReportQuery $query): ReportResult;
}
