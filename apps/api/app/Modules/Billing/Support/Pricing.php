<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * Plans and module prices — config/billing.php, overridden row by row by what platform admins save
 * (billing_catalog) — and what a monthly / yearly choice costs. The lifetime option is `Lifetime`.
 */
final class Pricing
{
    /** Every cycle an invoice may carry; shops choose from `cycles()`. */
    public const CYCLES = ['monthly', 'yearly'];

    private const CACHE = 'billing:catalog:v1';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly BillingSettings $settings,
        private readonly Cache $cache,
    ) {}

    /**
     * Every plan (closed ones too: old invoices name them), in the admins' order.
     *
     * @return array<string, array{name: string, description: string, monthly: int, lifetime: int, maintenance: int, modules: list<string>, featured: bool, enabled: bool}>
     */
    public function plans(): array
    {
        return $this->catalog()['plans'];
    }

    /** @return array<string, array{name: string, description: string, monthly: int, lifetime: int, maintenance: int, modules: list<string>, featured: bool, enabled: bool}> */
    public function openPlans(): array
    {
        return array_filter($this->plans(), fn (array $p) => $p['enabled']);
    }

    /**
     * Optional modules sold on top of a plan.
     *
     * @return array<string, array{monthly: int, lifetime: int, maintenance: int, enabled: bool}>
     */
    public function modules(): array
    {
        return $this->catalog()['modules'];
    }

    /** @return array<string, int> module key => monthly price (the modules on sale) */
    public function modulePrices(): array
    {
        return array_map(fn (array $m) => $m['monthly'], array_filter($this->modules(), fn (array $m) => $m['enabled']));
    }

    /** @return list<string> the cycles a shop may choose now (the lifetime option aside) */
    public function cycles(): array
    {
        return $this->settings->get()['yearly_enabled'] ? self::CYCLES : ['monthly'];
    }

    public function months(string $cycle): int
    {
        return $cycle === 'yearly' ? 12 : 1;
    }

    /** What the cycle costs, in months of the monthly price. */
    public function billedMonths(string $cycle): int
    {
        return $cycle === 'yearly' ? $this->settings->get()['yearly_months'] : 1;
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
            throw new DomainRuleException('اختار مدة الاشتراك.', 'cycle_invalid', 422);
        }

        $prices = $this->modulePrices();
        $extras = $this->extras($plan, $extras);
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

    /**
     * The extra modules of a choice: those the plan doesn't already include, once each.
     *
     * @param  list<string>  $extras
     * @return list<string>
     */
    public function extras(string $plan, array $extras): array
    {
        return array_values(array_unique(array_diff($extras, $this->plans()[$plan]['modules'] ?? [])));
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

    /**
     * Saves the admins' prices (every plan and module sent; a plan's included modules too).
     *
     * @param  array{plans: array<string, array<string, mixed>>, modules: array<string, array<string, mixed>>}  $data
     */
    public function save(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $position = 0;
            foreach ($data['plans'] as $key => $p) {
                DB::table('billing_catalog')->updateOrInsert(['kind' => 'plan', 'key' => $key], [
                    'name' => $p['name'], 'description' => $p['description'] ?? null,
                    'monthly' => (int) $p['monthly'], 'lifetime' => (int) $p['lifetime'], 'maintenance' => (int) $p['maintenance'],
                    'enabled' => (bool) $p['enabled'], 'featured' => (bool) ($p['featured'] ?? false),
                    'modules' => json_encode(array_values(array_unique($p['modules'] ?? []))), 'position' => $position++,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($data['modules'] as $key => $m) {
                DB::table('billing_catalog')->updateOrInsert(['kind' => 'module', 'key' => $key], [
                    'monthly' => (int) $m['monthly'], 'lifetime' => (int) $m['lifetime'], 'maintenance' => (int) $m['maintenance'],
                    'enabled' => (bool) $m['enabled'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $this->cache->forget(self::CACHE);
    }

    /** @return array{plans: array<string, array<string, mixed>>, modules: array<string, array<string, mixed>>} */
    private function catalog(): array
    {
        return $this->cache->rememberForever(self::CACHE, function (): array {
            $rows = DB::table('billing_catalog')->orderBy('position')->get()->groupBy('kind');
            $saved = fn (string $kind) => ($rows[$kind] ?? collect())->keyBy('key');

            $plans = [];
            $planRows = $saved('plan');
            foreach (config('billing.plans') as $key => $p) {
                $row = $planRows[$key] ?? null;
                $plans[$key] = [
                    'name' => $row->name ?? $p['name'],
                    'description' => $row->description ?? $p['description'],
                    'monthly' => (int) ($row->monthly ?? $p['monthly']),
                    'lifetime' => (int) ($row->lifetime ?? $p['lifetime'] ?? 0),
                    'maintenance' => (int) ($row->maintenance ?? $p['maintenance'] ?? 0),
                    'modules' => $row !== null && $row->modules !== null ? json_decode($row->modules, true) : $p['modules'],
                    'featured' => (bool) ($row->featured ?? $p['featured'] ?? false),
                    'enabled' => (bool) ($row->enabled ?? true),
                    'position' => $row->position ?? 1000,
                ];
            }
            uasort($plans, fn (array $a, array $b) => $a['position'] <=> $b['position']);
            $plans = array_map(function (array $p) {
                unset($p['position']);

                return $p;
            }, $plans);

            $modules = [];
            $moduleRows = $saved('module');
            $d = config('billing.lifetime');
            $round = fn (int $v) => (int) (round($v / 5000) * 5000);
            foreach (config('billing.modules') as $key => $monthly) {
                $row = $moduleRows[$key] ?? null;
                $modules[$key] = [
                    'monthly' => (int) ($row->monthly ?? $monthly),
                    'lifetime' => (int) ($row->lifetime ?? $round($monthly * (int) $d['module_months'])),
                    'maintenance' => (int) ($row->maintenance ?? $round($monthly * (int) $d['module_maintenance_months'])),
                    'enabled' => (bool) ($row->enabled ?? true),
                ];
            }

            return ['plans' => $plans, 'modules' => $modules];
        });
    }
}
