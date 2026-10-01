<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Http\Controllers;

use App\Modules\ModuleManager\FeatureGate;
use App\Modules\ModuleManager\Models\TenantFeature;
use App\Support\Audit\Auditor;
use App\Support\Modules\Feature;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\FeatureSetting;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The owner's "مميزات" page: every small switch of the modules the shop uses, grouped by module,
 * with its setting and whether it differs from its default. A choice equal to the default is not
 * kept, so "رجّع الافتراضي" is simply removing the override.
 */
final class FeatureController
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleAccess $modules,
        private readonly FeatureAccess $features,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = $this->tenant->idOrFail();
        $state = $this->features->all();
        $settings = $this->features->settings();
        $overrides = FeatureGate::overrides($tenantId);
        $groups = [];
        foreach ($this->registry->visible() as $module) {
            if ($module->features === [] || ! $this->modules->enabled($module->key)) {
                continue;
            }
            $groups[] = [
                'module' => $module->key,
                'name' => $module->name,
                'intro' => $module->featuresIntro ?? $module->description,
                'features' => array_map(function (Feature $f) use ($state, $settings, $overrides): array {
                    $enabled = $state[$f->key] ?? false;
                    $value = $settings[$f->key] ?? null;

                    return [
                        ...$f->toArray(),
                        'enabled' => $enabled,
                        'value' => $value,
                        'customized' => $enabled !== $f->default || ($f->setting !== null && $value !== $f->setting->default),
                        'updated_by_name' => $overrides[$f->key]['by'] ?? null,
                    ];
                }, $module->features),
            ];
        }

        return response()->json(['data' => $groups]);
    }

    /** `{enabled?, value?}`: the switch, its setting, or both. */
    public function update(Request $request, string $key, Auditor $audit): JsonResponse
    {
        $feature = $this->usable($key);
        $data = $request->validate([
            'enabled' => ['required_without:value', 'boolean'],
            'value' => $feature->setting === null
                ? ['prohibited']
                : ['required_without:enabled', 'nullable', ...$feature->setting->rules()],
        ], [], ['value' => $feature->setting?->label ?? 'القيمة']);
        $tenantId = $this->tenant->idOrFail();

        DB::transaction(function () use ($tenantId, $key, $data, $feature, $request, $audit): void {
            $row = TenantFeature::query()->where('feature_key', $key)->lockForUpdate()->first();
            $wasOn = $row !== null ? $row->enabled : $feature->default;
            $enabled = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : $wasOn;
            $value = $feature->setting === null ? null
                : $feature->setting->cast(array_key_exists('value', $data) ? $data['value'] : $row?->value);
            $isDefault = $enabled === $feature->default && ($feature->setting === null || $value === $feature->setting->default);

            if ($isDefault) {
                $row?->delete();
            } else {
                $row ??= new TenantFeature(['tenant_id' => $tenantId, 'feature_key' => $key]);
                $row->fill([
                    'enabled' => $enabled,
                    'value' => $feature->setting === null || $value === $feature->setting->default ? null : (string) $value,
                    'updated_by_name' => $request->user()?->getAttribute('name'),
                ])->save();
            }

            if ($enabled !== $wasOn) {
                $audit->record('features.'.($enabled ? 'enabled' : 'disabled'), ($enabled ? 'فتح' : 'قفل')." «{$feature->label}»", $row, ['feature' => $key]);
            }
            if (array_key_exists('value', $data) && $feature->setting !== null) {
                $audit->record('features.setting', "غيّر «{$feature->setting->label}» في «{$feature->label}» لـ «".$this->describe($feature->setting, $value).'»', $row, ['feature' => $key, 'value' => $value]);
            }
        });
        $this->features->forget($tenantId);

        return $this->index();
    }

    /** Back to the default (one switch). */
    public function reset(string $key, Auditor $audit): JsonResponse
    {
        $feature = $this->usable($key);

        return $this->resetKeys([$key => $feature], $audit, "رجّع «{$feature->label}» للافتراضي");
    }

    /** Back to the defaults (every switch of one module). */
    public function resetModule(string $module, Auditor $audit): JsonResponse
    {
        abort_unless($this->registry->has($module), 404);
        $manifest = $this->registry->get($module);
        abort_if($manifest->features === [] || ! $this->modules->enabled($module), 404);
        $features = [];
        foreach ($manifest->features as $f) {
            $features[$f->key] = $f;
        }

        return $this->resetKeys($features, $audit, "رجّع مميزات «{$manifest->name}» للافتراضي");
    }

    /**
     * @param  array<string, Feature>  $features
     */
    private function resetKeys(array $features, Auditor $audit, string $description): JsonResponse
    {
        $tenantId = $this->tenant->idOrFail();
        DB::transaction(function () use ($features, $audit, $description): void {
            $deleted = TenantFeature::query()->whereIn('feature_key', array_keys($features))->delete();
            if ($deleted > 0) {
                $audit->record('features.reset', $description, null, ['features' => array_keys($features)]);
            }
        });
        $this->features->forget($tenantId);

        return $this->index();
    }

    private function usable(string $key): Feature
    {
        $feature = $this->registry->feature($key) ?? abort(404);
        abort_unless($this->modules->enabled($this->registry->features()[$key]['module']), 404);

        return $feature;
    }

    private function describe(FeatureSetting $setting, int|string $value): string
    {
        return $setting->type === FeatureSetting::CHOICE
            ? ($setting->choices[(string) $value] ?? (string) $value)
            : trim($value.' '.($setting->unit ?? ''));
    }
}
