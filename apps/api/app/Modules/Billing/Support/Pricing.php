<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleRegistry;

/** Plans and module prices (config/billing.php), and what a choice costs. */
final class Pricing
{
    public const CYCLES = ['monthly', 'yearly'];

    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return array<string, array{name: string, description: string, monthly: int, modules: list<string>, featured?: bool}>
     */
    public function plans(): array
    {
        return config('billing.plans');
    }

    /** @return array<string, int> module key => monthly price */
    public function modulePrices(): array
    {
        return config('billing.modules');
    }

    public function months(string $cycle): int
    {
        return $cycle === 'yearly' ? 12 : 1;
    }

    /** What the cycle costs, in months of the monthly price. */
    public function billedMonths(string $cycle): int
    {
        return $cycle === 'yearly' ? (int) config('billing.yearly_months') : 1;
    }

    /**
     * @param  list<string>  $extras  modules on top of the plan
     * @return array{plan: string, cycle: string, modules: list<string>, months: int, lines: list<array{description: string, amount: int}>, total: int, vat: int}
     */
    public function quote(string $plan, string $cycle, array $extras = []): array
    {
        $plans = $this->plans();
        $info = $plans[$plan] ?? throw new DomainRuleException('الباقة دي مش موجودة.', 'plan_not_found', 422);
        if (! in_array($cycle, self::CYCLES, true)) {
            throw new DomainRuleException('اختار شهري أو سنوي.', 'cycle_invalid', 422);
        }

        $prices = $this->modulePrices();
        $extras = array_values(array_unique(array_diff($extras, $info['modules'])));
        foreach ($extras as $key) {
            if (! isset($prices[$key])) {
                throw new DomainRuleException('فيه قسم مش متاح للاشتراك.', 'module_not_sold', 422, ['module' => $key]);
            }
        }

        $billed = $this->billedMonths($cycle);
        $period = $cycle === 'yearly' ? 'سنة' : 'شهر';
        $lines = [['description' => "باقة «{$info['name']}» — {$period}", 'amount' => $info['monthly'] * $billed]];
        foreach ($extras as $key) {
            $lines[] = ['description' => "قسم «{$this->name($key)}» — {$period}", 'amount' => $prices[$key] * $billed];
        }
        $total = array_sum(array_column($lines, 'amount'));

        return [
            'plan' => $plan,
            'cycle' => $cycle,
            'modules' => $extras,
            'months' => $this->months($cycle),
            'lines' => $lines,
            'total' => $total,
            'vat' => $this->vatOf($total),
        ];
    }

    /** The VAT inside a VAT-inclusive amount. */
    public function vatOf(int $total): int
    {
        $rate = (int) config('billing.vat_basis_points');

        return (int) round($total * $rate / (10000 + $rate));
    }

    /**
     * @return list<string> the optional modules a subscription pays for
     */
    public function modulesOf(string $plan, array $extras): array
    {
        return array_values(array_unique([...($this->plans()[$plan]['modules'] ?? []), ...$extras]));
    }

    public function name(string $moduleKey): string
    {
        return $this->registry->has($moduleKey) ? $this->registry->get($moduleKey)->name : $moduleKey;
    }
}
