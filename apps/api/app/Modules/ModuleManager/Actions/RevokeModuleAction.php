<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Actions;

use App\Modules\ModuleManager\Events\ModuleDisabled;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Events\EventRecorder;
use App\Support\Modules\ModuleAccess;
use Illuminate\Support\Facades\DB;

/**
 * Billing removes a module from the subscription. The module becomes read-only; data is kept.
 */
final class RevokeModuleAction
{
    public function __construct(
        private readonly ModuleAccess $access,
        private readonly EventRecorder $events,
    ) {}

    public function handle(string $tenantId, string $key): void
    {
        DB::transaction(function () use ($tenantId, $key): void {
            $row = TenantModule::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->where('module_key', $key)
                ->lockForUpdate()
                ->first();

            if ($row === null || ! $row->entitled) {
                return;
            }

            $wasUsable = $row->effectiveState()->isUsable();
            $row->fill(['entitled' => false])->save();

            if ($wasUsable && ! $row->effectiveState()->isUsable()) {
                $this->events->record(new ModuleDisabled($tenantId, $key, 'subscription'));
            }

            $this->access->forget($tenantId);
            DB::afterCommit(fn () => $this->access->forget($tenantId));
        });
    }
}
