<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Support\BillingView;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * The plans and prices for the public website (muhasebi.com/pricing), straight from
 * config/billing.php so the site never shows a stale price. No sign-in.
 */
final class PublicPlansController extends Controller
{
    public function __invoke(BillingView $view, ModuleRegistry $registry): JsonResponse
    {
        // A module whose screens aren't built yet is shown as «قريباً»; one the platform hid isn't listed,
        // one it opened to every shop is marked free.
        $available = fn (string $key): bool => ! $registry->has($key) || $registry->get($key)->available;
        $hidden = fn (string $key): bool => $registry->has($key) && $registry->get($key)->hidden;
        $free = fn (string $key): bool => $registry->has($key) && $registry->get($key)->freeForAll;
        $mark = fn (array $modules): array => array_values(array_map(
            fn (array $m) => [...$m, 'available' => $available($m['key']), 'free' => $free($m['key'])],
            array_filter($modules, fn (array $m) => ! $hidden($m['key'])),
        ));

        return response()->json([
            'data' => [
                'trial_days' => (int) config('billing.trial_days'),
                'yearly_months' => (int) config('billing.yearly_months'),
                'vat_percent' => intdiv((int) config('billing.vat_basis_points'), 100),
                'plans' => array_map(fn (array $plan) => [...$plan, 'modules' => $mark($plan['modules'])], $view->catalog()),
                'modules' => $mark($view->extraModules()),
            ],
        ])->setPublic()->setMaxAge(300);
    }
}
