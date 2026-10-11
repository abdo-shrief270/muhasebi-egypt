<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\ShopNeeds;

/**
 * «بتعمل إيه في المحل؟»: the needs a shop ticks at registration (config `billing.needs`), the
 * modules that cover them (its trial starts with those) and the cheapest plan + extra modules that
 * covers them at today's prices — what the website, the sign-up form and the billing page suggest.
 */
final class Needs implements ShopNeeds
{
    public function __construct(private readonly Pricing $pricing) {}

    /** @return list<array{key: string, label: string, description: string, icon: string, types: list<string>}> */
    public function all(): array
    {
        $out = [];
        foreach (config('billing.needs') as $key => $need) {
            $out[] = ['key' => $key, 'label' => $need['label'], 'description' => $need['description'], 'icon' => $need['icon'], 'types' => $need['types']];
        }

        return $out;
    }

    public function keys(): array
    {
        return array_keys(config('billing.needs'));
    }

    public function modulesFor(array $needs): array
    {
        $all = config('billing.needs');
        $modules = [];
        foreach ($needs as $need) {
            array_push($modules, ...($all[$need]['modules'] ?? []));
        }

        return array_values(array_unique($modules));
    }

    /**
     * The cheapest plan on sale (by its monthly price with the extras it needs) covering the
     * needs; a tie goes to the plan with fewer extras. Lifetime / maintenance only when that plan
     * is sold for life.
     *
     * @param  list<string>  $needs
     * @return array{plan: string, plan_name: string, modules: list<array{key: string, name: string}>, monthly: int, lifetime: ?int, maintenance: ?int}|null
     */
    public function recommend(array $needs): ?array
    {
        $wanted = $this->modulesFor($needs);
        $modules = $this->pricing->modules();
        $best = null;
        foreach ($this->pricing->openPlans() as $key => $plan) {
            $extras = array_values(array_diff($wanted, $plan['modules']));
            if (array_filter($extras, fn (string $m) => ! isset($modules[$m]) || ! $modules[$m]['enabled']) !== []) {
                continue;
            }
            $monthly = $plan['monthly'] + array_sum(array_map(fn (string $m) => $modules[$m]['monthly'], $extras));
            if ($best === null || $monthly < $best['monthly'] || ($monthly === $best['monthly'] && count($extras) < count($best['extras']))) {
                $best = ['plan' => $key, 'info' => $plan, 'extras' => $extras, 'monthly' => $monthly];
            }
        }
        if ($best === null) {
            return null;
        }
        $forLife = $best['info']['lifetime'] > 0 && array_filter($best['extras'], fn (string $m) => $modules[$m]['lifetime'] <= 0) === [];

        return [
            'plan' => $best['plan'],
            'plan_name' => $best['info']['name'],
            'modules' => array_map(fn (string $m) => ['key' => $m, 'name' => $this->pricing->name($m)], $best['extras']),
            'monthly' => $best['monthly'],
            'lifetime' => $forLife ? $best['info']['lifetime'] + array_sum(array_map(fn (string $m) => $modules[$m]['lifetime'], $best['extras'])) : null,
            'maintenance' => $forLife ? $best['info']['maintenance'] + array_sum(array_map(fn (string $m) => $modules[$m]['maintenance'], $best['extras'])) : null,
        ];
    }
}
