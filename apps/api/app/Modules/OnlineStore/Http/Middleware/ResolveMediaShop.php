<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A store's logo / cover belongs to the shop named in its path (stores/{tenant}/…), so
 * `module:online_store` can check that shop still uses the module.
 */
final class ResolveMediaShop
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenant->set(explode('/', (string) $request->route('path'))[0]);

        return $next($request);
    }
}
