<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Support\Exceptions\DomainRuleException;
use Closure;
use Illuminate\Database\Eloquent\Model;
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
        $user = $request->user();
        // Read raw: a platform admin's token is a different model, with no shop.
        $attributes = $user instanceof Model ? $user->getAttributes() : [];
        $tenantId = $attributes['tenant_id'] ?? null;

        abort_if($tenantId === null, Response::HTTP_FORBIDDEN, 'لا يوجد محل مرتبط بالحساب.');

        if (($attributes['is_active'] ?? true) === false) {
            throw new DomainRuleException('الحساب ده اتوقف. كلّم صاحب المحل.', 'user_inactive', Response::HTTP_FORBIDDEN);
        }

        $this->tenant->set($tenantId);

        foreach (app()->tagged(TenantRequestGuard::TAG) as $guard) {
            /** @var TenantRequestGuard $guard */
            $guard->check($request, (string) $tenantId);
        }

        return $next($request);
    }
}
