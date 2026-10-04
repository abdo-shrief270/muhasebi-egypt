<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Support;

use App\Modules\ModuleManager\Actions\GrantModuleAction;
use App\Modules\ModuleManager\Actions\ModuleRules;
use App\Modules\ModuleManager\Actions\RevokeModuleAction;
use App\Modules\ModuleManager\Contracts\PlatformModules;
use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\Feature;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleState;
use App\Support\Modules\ModuleTier;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * `platform_modules` / `platform_features`: what the platform admin changed for every shop. Read by
 * the ModuleRegistry (cached as arrays); a missing row = the manifest's own value.
 */
final class PlatformSettings implements PlatformModules
{
    private const CACHE_KEY = 'platform:modules:v1';

    public const STATUSES = ['live', 'coming_soon', 'hidden', 'free'];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Cache $cache,
    ) {}

    /** @return array{modules: array<string, array<string, mixed>>, features: array<string, array<string, mixed>>} */
    public function overrides(): array
    {
        return $this->cache->remember(self::CACHE_KEY, 600, function (): array {
            try {
                $modules = DB::table('platform_modules')->get()->mapWithKeys(fn (object $r) => [$r->module_key => [
                    'status' => $r->status,
                    'trial_allowed' => $r->trial_allowed === null ? null : (bool) $r->trial_allowed,
                    'auto_trial' => $r->auto_trial === null ? null : (bool) $r->auto_trial,
                    'trial_days' => $r->trial_days,
                    'shop_types' => $r->shop_types === null ? null : json_decode($r->shop_types, true),
                    'name' => $r->name,
                    'description' => $r->description,
                    'by' => $r->updated_by_name,
                    'at' => $r->updated_at,
                ]])->all();
                $features = DB::table('platform_features')->get()->mapWithKeys(fn (object $r) => [$r->feature_key => [
                    'mode' => $r->mode,
                    'default' => $r->default === null ? null : (bool) $r->default,
                    'by' => $r->updated_by_name,
                ]])->all();
            } catch (QueryException) {
                return ['modules' => [], 'features' => []];   // before the migration ran
            }

            return ['modules' => $modules, 'features' => $features];
        });
    }

    public function catalog(): array
    {
        $overrides = $this->overrides();
        $usage = DB::table('tenant_modules')
            ->selectRaw("module_key, count(*) filter (where state = 'enabled' and entitled) as paid, count(*) filter (where state = 'trial' and trial_ends_at > now()) as trial")
            ->groupBy('module_key')->get()->keyBy('module_key');

        $out = [];
        foreach ($this->registry->all() as $key => $module) {
            if ($module->tier === ModuleTier::Platform) {
                continue;
            }
            $declared = $this->registry->declared($key);
            $row = $overrides['modules'][$key] ?? [];
            $out[] = [
                'key' => $key,
                'name' => $module->name,
                'description' => $module->description,
                'declared' => ['name' => $declared->name, 'description' => $declared->description, 'status' => $declared->available ? 'live' : 'coming_soon', 'shop_types' => $declared->shopTypes, 'auto_trial' => $declared->shopTypes !== [] && $declared->trialFor !== []],
                'optional' => $module->isOptional(),
                'status' => $this->status($module),
                'status_overridden' => ($row['status'] ?? null) !== null,
                'trial_allowed' => $module->trialAllowed,
                'auto_trial' => $module->shopTypes !== [] && $module->trialFor !== [] && $module->trialAllowed,
                'auto_trial_overridden' => ($row['auto_trial'] ?? null) !== null,
                'trial_days' => $module->trialDays ?? TenantModule::TRIAL_DAYS,
                'shop_types' => $module->shopTypes,
                'shop_types_overridden' => ($row['shop_types'] ?? null) !== null,
                'trial_for' => $module->trialFor,
                'depends_on' => $module->dependsOn,
                'shops' => ['paid' => (int) ($usage[$key]->paid ?? 0), 'trial' => (int) ($usage[$key]->trial ?? 0)],
                'updated_by_name' => $row['by'] ?? null,
                'features' => array_map(fn (Feature $f): array => [
                    'key' => $f->key,
                    'label' => $f->label,
                    'description' => $f->description,
                    'default' => $f->default,
                    'declared_default' => $this->declaredFeature($declared, $f->key)?->default ?? $f->default,
                    'mode' => match ($f->forced) {
                        true => 'on', false => 'off', null => 'shop'
                    },
                    'updated_by_name' => $overrides['features'][$f->key]['by'] ?? null,
                ], $module->features),
            ];
        }

        return $out;
    }

    public function updateModule(string $key, array $data, ?string $byName): void
    {
        $module = $this->known($key);
        if (! $module->isOptional() && array_intersect(array_keys($data), ['status', 'trial_allowed', 'auto_trial', 'trial_days', 'shop_types']) !== []) {
            throw new DomainRuleException('القسم ده أساسي في كل المحلات: تقدر تغيّر اسمه ووصفه ومميزاته بس.', 'module_not_optional');
        }
        $current = (array) (DB::table('platform_modules')->where('module_key', $key)->first() ?? []);
        $columns = ['status', 'trial_allowed', 'auto_trial', 'trial_days', 'shop_types', 'name', 'description'];
        $values = [];
        foreach ($columns as $c) {
            $value = array_key_exists($c, $data) ? $data[$c] : ($current[$c] ?? null);
            if ($c === 'shop_types' && is_array($value)) {
                $value = json_encode(array_values($value));
            }
            if (in_array($c, ['name', 'description'], true) && is_string($value) && trim($value) === '') {
                $value = null;
            }
            $values[$c] = $value;
        }
        if (array_filter($values, fn ($v) => $v !== null) === []) {
            DB::table('platform_modules')->where('module_key', $key)->delete();
        } else {
            DB::table('platform_modules')->updateOrInsert(['module_key' => $key], [...$values, 'updated_by_name' => $byName, 'updated_at' => now(), 'created_at' => $current['created_at'] ?? now()]);
        }
        $this->changed();
    }

    public function updateFeature(string $key, array $data, ?string $byName): void
    {
        if ($this->registry->feature($key) === null) {
            throw new DomainRuleException('الميزة دي مش موجودة.', 'feature_unknown', 404);
        }
        $current = DB::table('platform_features')->where('feature_key', $key)->first();
        $mode = $data['mode'] ?? $current?->mode ?? 'shop';
        $default = array_key_exists('default', $data) ? $data['default'] : ($current?->default === null ? null : (bool) $current->default);
        if ($mode === 'shop' && $default === null) {
            DB::table('platform_features')->where('feature_key', $key)->delete();
        } else {
            DB::table('platform_features')->updateOrInsert(['feature_key' => $key], ['mode' => $mode, 'default' => $default, 'updated_by_name' => $byName, 'updated_at' => now(), 'created_at' => $current->created_at ?? now()]);
        }
        $this->changed();
    }

    public function shopModules(string $tenantId): array
    {
        $rows = TenantModule::withoutTenancy()->where('tenant_id', $tenantId)->get()->keyBy('module_key');
        $access = app(ModuleAccess::class);
        $out = [];
        foreach ($this->registry->all() as $key => $module) {
            if (! $module->isOptional() || $module->hidden) {
                continue;
            }
            $row = $rows->get($key);
            $state = $access->state($key, $tenantId);
            $out[] = [
                'key' => $key,
                'name' => $module->name,
                'status' => $this->status($module),
                'state' => $state->value,
                'state_label' => $state->label(),
                'usable' => $module->available && $state->isUsable(),
                'entitled' => (bool) $row?->entitled,
                'source' => $row?->source,
                'trial_ends_at' => $row?->trial_ends_at?->toIso8601String(),
                'trial_used' => $row?->trial_started_at !== null,
            ];
        }

        return $out;
    }

    public function setShopModule(string $tenantId, string $key, string $action): void
    {
        $module = app(ModuleRules::class)->optionalModule($key);
        match ($action) {
            'open' => app(GrantModuleAction::class)->handle($tenantId, $key, 'admin'),
            'close' => $this->close($tenantId, $key),
            'trial' => $this->freshTrial($tenantId, $module),
            default => throw new DomainRuleException('إجراء غير معروف.', 'action_unknown'),
        };
        app(ModuleAccess::class)->forget($tenantId);
        app(FeatureAccess::class)->forget($tenantId);
    }

    private function close(string $tenantId, string $key): void
    {
        app(RevokeModuleAction::class)->handle($tenantId, $key);
        TenantModule::withoutTenancy()->where('tenant_id', $tenantId)->where('module_key', $key)
            ->where('trial_ends_at', '>', now())->update(['trial_ends_at' => now()]);
    }

    /** A new trial for this shop, even if it had one before (the admin's gift). */
    private function freshTrial(string $tenantId, ModuleManifest $module): void
    {
        DB::transaction(function () use ($tenantId, $module): void {
            $row = TenantModule::withoutTenancy()->where('tenant_id', $tenantId)->where('module_key', $module->key)->lockForUpdate()->first()
                ?? new TenantModule(['tenant_id' => $tenantId, 'module_key' => $module->key, 'entitled' => false]);
            if ($row->exists && $row->entitled && $row->effectiveState()->isUsable()) {
                throw new DomainRuleException('المحل مشترك في القسم ده أصلاً.', 'module_already_enabled');
            }
            $now = now()->toImmutable();
            $row->fill([
                'state' => ModuleState::Trial,
                'source' => 'trial',
                'trial_started_at' => $now,
                'trial_ends_at' => $now->addDays($module->trialDays ?? TenantModule::TRIAL_DAYS),
                'enabled_at' => $now,
                'disabled_at' => null,
            ])->save();
            app(EventRecorder::class)->record(new ModuleEnabled($tenantId, $module->key, 'trial'));
        });
    }

    private function status(ModuleManifest $m): string
    {
        return match (true) {
            $m->hidden => 'hidden',
            ! $m->available => 'coming_soon',
            $m->freeForAll => 'free',
            default => 'live',
        };
    }

    private function known(string $key): ModuleManifest
    {
        if (! $this->registry->has($key) || $this->registry->get($key)->tier === ModuleTier::Platform) {
            throw new DomainRuleException('القسم غير موجود.', 'module_unknown', 404);
        }

        return $this->registry->get($key);
    }

    private function declaredFeature(ModuleManifest $declared, string $key): ?Feature
    {
        foreach ($declared->features as $f) {
            if ($f->key === $key) {
                return $f;
            }
        }

        return null;
    }

    /** Shops' cached rows are their own choices only; the registry applies these on top. */
    private function changed(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        $this->registry->flushOverrides();
        app()->forgetScopedInstances();   // the module / feature gates' per-request memos
    }
}
