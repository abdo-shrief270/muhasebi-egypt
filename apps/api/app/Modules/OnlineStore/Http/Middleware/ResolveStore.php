<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Middleware;

use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Support\Slugs;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public store has no login: the shop is the one whose store has this slug (from the route, or
 * from a `domain` like elnour.muhasebi.com — or the shop's own verified domain), and only while it's open. Runs before
 * `module:online_store`, so a shop that stops the module closes its store.
 */
final class ResolveStore
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next, string $when = 'open'): Response
    {
        $store = $request->route('slug') !== null
            ? OnlineStore::withoutTenancy()->where('slug', strtolower((string) $request->route('slug')))->first()
            : $this->byHost(strtolower(rtrim((string) $request->query('domain', ''), '.')));
        // `any`: a placed order's tracking page outlives the store being closed.
        if ($store === null || ($when !== 'any' && ! $store->isOpen())) {
            return response()->json(['message' => 'المتجر ده مش موجود أو مقفول دلوقتي.', 'code' => 'store_not_found'], 404);
        }
        $this->tenant->set($store->tenant_id);
        $request->attributes->set('online_store', $store);

        return $next($request);
    }

    private function byHost(string $host): ?OnlineStore
    {
        if ($host === '') {
            return null;
        }
        $slug = Slugs::fromHost($host);

        return $slug !== null
            ? OnlineStore::withoutTenancy()->where('slug', $slug)->first()
            : OnlineStore::withoutTenancy()->where('custom_domain', $host)->whereNotNull('custom_domain_verified_at')->first();
    }
}
