<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Support;

use App\Support\Exceptions\DomainRuleException;
use Carbon\CarbonImmutable;

/**
 * A pay period in Cairo calendar days: a month, or a week from Saturday to Friday. `from` / `to`
 * are the instants (UTC) that bound it, for timestamps such as tickets delivered.
 */
final readonly class Period
{
    public function __construct(
        public string $cycle,
        public CarbonImmutable $starts,
        public CarbonImmutable $ends,
    ) {}

    public static function of(string $cycle, ?string $date = null): self
    {
        $day = $date === null ? CarbonImmutable::now('Africa/Cairo') : CarbonImmutable::parse($date, 'Africa/Cairo');

        return match ($cycle) {
            'monthly' => new self('monthly', $day->startOfMonth(), $day->endOfMonth()->startOfDay()),
            'weekly' => new self('weekly', $day->startOfWeek(CarbonImmutable::SATURDAY), $day->startOfWeek(CarbonImmutable::SATURDAY)->addDays(6)),
            default => throw new DomainRuleException('نوع الفترة مش معروف.', 'payroll_cycle'),
        };
    }

    public function from(): CarbonImmutable
    {
        return $this->starts->startOfDay()->utc();
    }

    public function to(): CarbonImmutable
    {
        return $this->ends->endOfDay()->utc();
    }

    public function previous(): self
    {
        return self::of($this->cycle, $this->starts->subDay()->toDateString());
    }

    public function next(): self
    {
        return self::of($this->cycle, $this->ends->addDay()->toDateString());
    }

    /** "أكتوبر 2026" / "أسبوع 17 – 23 أكتوبر" */
    public function label(): string
    {
        $month = fn (CarbonImmutable $d) => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'][$d->month - 1];

        return $this->cycle === 'monthly'
            ? $month($this->starts).' '.$this->starts->year
            : "أسبوع {$this->starts->day} {$month($this->starts)} – {$this->ends->day} {$month($this->ends)}";
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'cycle' => $this->cycle,
            'starts_on' => $this->starts->toDateString(),
            'ends_on' => $this->ends->toDateString(),
            'label' => $this->label(),
            'previous' => $this->previous()->starts->toDateString(),
            'next' => $this->next()->starts->toDateString(),
        ];
    }
}
