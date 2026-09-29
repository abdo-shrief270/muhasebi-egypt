<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Http\Request;

/**
 * Runs on every signed-in shop request once the shop is known (e.g. Billing: a suspended
 * subscription is read-only). Throw a DomainRuleException to refuse the request.
 * Implementations are tagged with self::TAG in a module's service provider.
 */
interface TenantRequestGuard
{
    public const TAG = 'tenant.request_guards';

    public function check(Request $request, string $tenantId): void;
}
