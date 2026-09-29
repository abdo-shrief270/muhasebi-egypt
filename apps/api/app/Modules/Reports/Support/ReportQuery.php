<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/** What a report is asked for: a period (Cairo days), branches, and what the user may see. */
final readonly class ReportQuery
{
    public const TZ = 'Africa/Cairo';

    /**
     * @param  array<string, string>  $branches  selected branches, id => name
     * @param  array<string, string>  $options  the report's own options (group by …)
     */
    public function __construct(
        public string $tenantId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $branches,
        public bool $withProfit,
        public bool $withCost,
        public array $options = [],
    ) {}

    public function fromUtc(): CarbonImmutable
    {
        return $this->from->startOfDay()->utc();
    }

    public function toUtc(): CarbonImmutable
    {
        return $this->to->endOfDay()->utc();
    }

    /**
     * @return list<string>
     */
    public function branchIds(): array
    {
        return array_keys($this->branches);
    }

    public function option(string $key, string $default): string
    {
        return $this->options[$key] ?? $default;
    }

    /** Restricts a query to the period on a timestamp column. */
    public function inPeriod(Builder $query, string $column): Builder
    {
        return $query->whereBetween($column, [$this->fromUtc(), $this->toUtc()]);
    }

    /** SQL for a timestamp column as a Cairo calendar day. */
    public static function localDay(string $column): string
    {
        return "(({$column}) at time zone 'UTC' at time zone '".self::TZ."')::date";
    }
}
