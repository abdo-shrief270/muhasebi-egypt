<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current tenant from the authenticated user. Runs after auth:sanctum.
 */
final class ResolveTenant
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user()?->getAttribute('tenant_id');

        abort_if($tenantId === null, Response::HTTP_FORBIDDEN, 'لا يوجد محل مرتبط بالحساب.');

        $this->tenant->set($tenantId);

        return $next($request);
    }
}
