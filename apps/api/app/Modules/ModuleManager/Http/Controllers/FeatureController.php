<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Http\Controllers;

use App\Modules\ModuleManager\Models\TenantFeature;
use App\Support\Audit\Auditor;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The owner's "مميزات" page: every small switch of the modules the shop uses. */
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
        $state = $this->features->all();
        $groups = [];
        foreach ($this->registry->visible() as $module) {
            if ($module->features === [] || ! $this->modules->enabled($module->key)) {
                continue;
            }
            $groups[] = [
                'module' => $module->key,
                'name' => $module->name,
                'features' => array_map(fn ($f) => [...$f->toArray(), 'enabled' => $state[$f->key] ?? false], $module->features),
            ];
        }

        return response()->json(['data' => $groups]);
    }

    public function update(Request $request, string $key, Auditor $audit): JsonResponse
    {
        $feature = $this->registry->feature($key) ?? abort(404);
        $module = $this->registry->features()[$key]['module'];
        abort_unless($this->modules->enabled($module), 404);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $tenantId = $this->tenant->idOrFail();

        DB::transaction(function () use ($tenantId, $key, $data, $feature, $request, $audit): void {
            $row = TenantFeature::query()->updateOrCreate(
                ['feature_key' => $key],
                ['tenant_id' => $tenantId, 'enabled' => (bool) $data['enabled'], 'updated_by_name' => $request->user()?->getAttribute('name')],
            );
            $audit->record('features.'.($row->enabled ? 'enabled' : 'disabled'), ($row->enabled ? 'فتح' : 'قفل')." «{$feature->label}»", $row);
        });
        $this->features->forget($tenantId);

        return $this->index();
    }
}
