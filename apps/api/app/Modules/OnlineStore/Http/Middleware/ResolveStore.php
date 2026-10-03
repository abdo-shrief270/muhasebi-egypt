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
 * from a `domain` like elnour.muhasebi.com), and only while it's open. Runs before
 * `module:online_store`, so a shop that stops the module closes its store.
 */
final class ResolveStore
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug') !== null
            ? strtolower((string) $request->route('slug'))
            : Slugs::fromHost((string) $request->query('domain', ''));
        $store = $slug === null ? null : OnlineStore::withoutTenancy()->where('slug', $slug)->first();
        if ($store === null || ! $store->isOpen()) {
            return response()->json(['message' => 'المتجر ده مش موجود أو مقفول دلوقتي.', 'code' => 'store_not_found'], 404);
        }
        $this->tenant->set($store->tenant_id);
        $request->attributes->set('online_store', $store);

        return $next($request);
    }
}
