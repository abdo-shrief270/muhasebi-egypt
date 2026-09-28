<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Actions;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleState;
use Illuminate\Support\Facades\DB;

/**
 * The owner turns back on a module they had hidden: allowed while it's in the subscription
 * or its trial is still running.
 */
final class EnableModuleAction
{
    public function __construct(
        private readonly ModuleRules $rules,
        private readonly ModuleAccess $access,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $tenantId, string $key): TenantModule
    {
        $module = $this->rules->optionalModule($key);
        $this->rules->assertDependenciesUsable($module, $tenantId);

        return DB::transaction(function () use ($tenantId, $key, $module): TenantModule {
            $row = TenantModule::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->where('module_key', $key)
                ->lockForUpdate()
                ->first();

            $trialRunning = $row?->trial_ends_at?->isFuture() ?? false;

            if ($row === null || (! $row->entitled && ! $trialRunning)) {
                throw new DomainRuleException(
                    "قسم «{$module->name}» مش ضمن اشتراكك. جرّبه أو ضيفه للاشتراك.",
                    'module_not_entitled',
                    403,
                );
            }

            if ($row->effectiveState()->isUsable()) {
                return $row;
            }

            $row->fill([
                'state' => $row->entitled ? ModuleState::Enabled : ModuleState::Trial,
                'enabled_at' => now(),
                'disabled_at' => null,
            ])->save();

            $this->audit->record('modules.enabled', "أظهر قسم «{$module->name}»", $row, tenantId: $tenantId);
            $this->events->record(new ModuleEnabled($tenantId, $key, $row->source));
            $this->access->forget($tenantId);
            DB::afterCommit(fn () => $this->access->forget($tenantId));

            return $row;
        });
    }
}
