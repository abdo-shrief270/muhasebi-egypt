<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Billing\Support\SubscriptionStatus;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\TenantRequestGuard;
use Illuminate\Http\Request;

/**
 * Applies the subscription to every shop request. The shop never stops selling before suspension:
 *  - restricted: no new products, staff or branches (growth, not daily work);
 *  - suspended: read-only, except paying (billing) and signing in/out.
 */
final class SubscriptionGuard implements TenantRequestGuard
{
    /** POST routes a restricted shop can't use. */
    private const GROWTH = ['api/v1/products', 'api/v1/products/import', 'api/v1/users', 'api/v1/branches'];

    /** Always allowed, whatever the status. */
    private const ALWAYS = ['api/v1/billing', 'api/v1/auth/'];

    public function __construct(private readonly Subscriptions $subscriptions) {}

    public function check(Request $request, string $tenantId): void
    {
        if ($request->isMethodSafe()) {
            return;
        }
        $uri = (string) $request->route()?->uri();
        foreach (self::ALWAYS as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return;
            }
        }

        $status = SubscriptionStatus::from($this->subscriptions->state($tenantId)['status']);

        if ($status === SubscriptionStatus::Suspended) {
            throw new DomainRuleException('الاشتراك موقوف: تقدر تتفرج وتصدّر بس. جدّد الاشتراك عشان ترجع تشتغل.', 'subscription_suspended', 402);
        }
        if ($status === SubscriptionStatus::Restricted && $request->isMethod('POST') && in_array($uri, self::GROWTH, true)) {
            throw new DomainRuleException('الاشتراك خلص من فترة: البيع شغال، بس مينفعش تضيف أصناف أو موظفين أو فروع لحد ما تجدّد.', 'subscription_restricted', 402);
        }
    }
}
