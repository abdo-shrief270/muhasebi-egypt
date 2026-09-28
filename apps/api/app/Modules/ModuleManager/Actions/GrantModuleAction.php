<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Actions;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Events\EventRecorder;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleState;
use Illuminate\Support\Facades\DB;

/**
 * Billing (or a platform admin) adds a module to the shop's subscription.
 * A module the owner had hidden stays hidden; otherwise it becomes enabled.
 */
final class GrantModuleAction
{
    public function __construct(
        private readonly ModuleRules $rules,
        private readonly ModuleAccess $access,
        private readonly EventRecorder $events,
    ) {}

    public function handle(string $tenantId, string $key, string $source): TenantModule
    {
        $this->rules->optionalModule($key);

        return DB::transaction(function () use ($tenantId, $key, $source): TenantModule {
            $row = TenantModule::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->where('module_key', $key)
                ->lockForUpdate()
                ->first() ?? new TenantModule(['tenant_id' => $tenantId, 'module_key' => $key]);

            $wasUsable = $row->exists && $row->effectiveState()->isUsable();
            $hidden = $row->exists && $row->state === ModuleState::Disabled;

            $row->fill([
                'entitled' => true,
                'source' => $source,
                'state' => $hidden ? ModuleState::Disabled : ModuleState::Enabled,
                'enabled_at' => $hidden ? $row->enabled_at : ($row->enabled_at ?? now()),
            ])->save();

            if (! $wasUsable && ! $hidden) {
                $this->events->record(new ModuleEnabled($tenantId, $key, $source));
            }

            $this->access->forget($tenantId);
            DB::afterCommit(fn () => $this->access->forget($tenantId));

            return $row;
        });
    }
}
