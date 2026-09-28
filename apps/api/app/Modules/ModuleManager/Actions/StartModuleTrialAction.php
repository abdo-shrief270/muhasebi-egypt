<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Actions;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleState;
use Illuminate\Support\Facades\DB;

final class StartModuleTrialAction
{
    public function __construct(
        private readonly ModuleRules $rules,
        private readonly ModuleAccess $access,
        private readonly EventRecorder $events,
    ) {}

    public function handle(string $tenantId, string $key): TenantModule
    {
        $module = $this->rules->optionalModule($key);
        $this->rules->assertDependenciesUsable($module, $tenantId);

        return DB::transaction(function () use ($tenantId, $key): TenantModule {
            $row = TenantModule::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->where('module_key', $key)
                ->lockForUpdate()
                ->first() ?? new TenantModule(['tenant_id' => $tenantId, 'module_key' => $key, 'entitled' => false]);

            if ($row->exists && $row->effectiveState()->isUsable()) {
                throw new DomainRuleException('القسم مفعّل بالفعل.', 'module_already_enabled');
            }

            if ($row->trial_started_at !== null) {
                throw new DomainRuleException('فترة التجربة لهذا القسم اتستخدمت قبل كده.', 'module_trial_used');
            }

            $now = now()->toImmutable();
            $row->fill([
                'state' => ModuleState::Trial,
                'source' => 'trial',
                'trial_started_at' => $now,
                'trial_ends_at' => $now->addDays(TenantModule::TRIAL_DAYS),
                'enabled_at' => $now,
                'disabled_at' => null,
            ])->save();

            $this->events->record(new ModuleEnabled($tenantId, $key, 'trial'));
            DB::afterCommit(fn () => $this->access->forget($tenantId));
            $this->access->forget($tenantId);

            return $row;
        });
    }
}
