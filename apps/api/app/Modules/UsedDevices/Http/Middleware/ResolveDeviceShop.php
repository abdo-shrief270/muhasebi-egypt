<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Http\Middleware;

use App\Modules\UsedDevices\Models\UsedDevice;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A device photo in «سوق محاسبي» has no login: the shop is the one that owns the device. Runs
 * before `module:used_devices`, so a shop that turned the module off stops showing them.
 */
final class ResolveDeviceShop
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = UsedDevice::withoutTenancy()->whereKey((string) $request->route('device'))->value('tenant_id');
        abort_if($tenantId === null, 404);
        $this->tenant->set((string) $tenantId);

        return $next($request);
    }
}
