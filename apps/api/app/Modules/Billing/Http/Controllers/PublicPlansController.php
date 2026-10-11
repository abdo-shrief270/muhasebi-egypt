<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Support\BillingView;
use App\Modules\Billing\Support\Lifetime;
use App\Modules\Billing\Support\Needs;
use App\Modules\Billing\Support\Pricing;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * The plans and prices for the public website (muhasebi.com/pricing), straight from
 * config/billing.php so the site never shows a stale price. No sign-in.
 */
final class PublicPlansController extends Controller
{
    public function __invoke(BillingView $view, ModuleRegistry $registry, Lifetime $lifetime, Pricing $pricing, Needs $needs): JsonResponse
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
                'yearly' => in_array('yearly', $pricing->cycles(), true),
                'yearly_months' => $pricing->billedMonths('yearly'),
                'vat_percent' => intdiv((int) config('billing.vat_basis_points'), 100),
                'plans' => array_map(fn (array $plan) => [...$plan, 'modules' => $mark($plan['modules'])], $view->catalog()),
                'modules' => $mark($view->extraModules()),
                // «مدى الحياة» while it's switched on (null otherwise).
                'lifetime' => $lifetime->publicOffer(),
                // «بتعمل إيه في المحل؟» for the sign-up form (and `recommend` for its price).
                'needs' => $needs->all(),
            ],
        ])->setPublic()->setMaxAge(60);
    }

    /** The cheapest plan + extras covering what the shop does (the sign-up form and the website ask). */
    public function recommend(Request $request, Needs $needs): JsonResponse
    {
        $data = $request->validate([
            'needs' => ['nullable', 'array', 'max:30'],
            'needs.*' => ['string', Rule::in($needs->keys())],
        ]);

        return response()->json(['data' => $needs->recommend(array_values(array_unique($data['needs'] ?? [])))])->setPublic()->setMaxAge(60);
    }
}
