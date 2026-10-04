<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Actions;

use App\Modules\ModuleManager\Events\ModuleDisabled;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleState;
use Illuminate\Support\Facades\DB;

/**
 * The owner hides a module. Data is never deleted; enabling it again brings everything back.
 */
final class DisableModuleAction
{
    public function __construct(
        private readonly ModuleRules $rules,
        private readonly ModuleAccess $access,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $tenantId, string $key): ?TenantModule
    {
        $module = $this->rules->optionalModule($key);
        $this->rules->assertNoUsableDependents($module, $tenantId);

        return DB::transaction(function () use ($tenantId, $key, $module): ?TenantModule {
            $row = TenantModule::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->where('module_key', $key)
                ->lockForUpdate()
                ->first();

            if ($row === null && $module->freeForAll) {
                $row = new TenantModule(['tenant_id' => $tenantId, 'module_key' => $key, 'entitled' => false, 'source' => 'free', 'state' => ModuleState::Enabled]);
            } elseif ($row === null || (! $module->freeForAll && ! $row->effectiveState()->isUsable()) || $row->state === ModuleState::Disabled) {
                return $row;
            }

            $row->fill(['state' => ModuleState::Disabled, 'disabled_at' => now()])->save();

            $this->audit->record('modules.disabled', "أخفى قسم «{$module->name}»", $row, tenantId: $tenantId);
            $this->events->record(new ModuleDisabled($tenantId, $key, 'owner'));
            $this->access->forget($tenantId);
            DB::afterCommit(fn () => $this->access->forget($tenantId));

            return $row;
        });
    }
}
