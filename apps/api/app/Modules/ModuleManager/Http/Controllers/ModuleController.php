<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Http\Controllers;

use App\Modules\Identity\Contracts\ShopProfile;
use App\Modules\ModuleManager\Actions\DisableModuleAction;
use App\Modules\ModuleManager\Actions\EnableModuleAction;
use App\Modules\ModuleManager\Actions\StartModuleTrialAction;
use App\Modules\ModuleManager\Http\Requests\ManageModulesRequest;
use App\Modules\ModuleManager\Http\Resources\ModuleResource;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ModuleController
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleAccess $access,
        private readonly CurrentTenant $tenant,
    ) {}

    /** The modules made for the shop's types, plus any it already uses. */
    public function index(ShopProfile $shop): AnonymousResourceCollection
    {
        $rows = TenantModule::query()->get()->keyBy('module_key');
        $types = $shop->types();

        $items = array_map(fn (ModuleManifest $module): array => [
            'module' => $module,
            'state' => $this->access->state($module->key),
            'row' => $rows->get($module->key),
        ], array_values(array_filter(
            $this->registry->visible(),
            fn (ModuleManifest $m) => $m->isFor($types) || $this->access->state($m->key)->isUsable(),
        )));

        return ModuleResource::collection($items);
    }

    public function trial(ManageModulesRequest $request, string $key, StartModuleTrialAction $action): ModuleResource
    {
        $action->handle($this->tenant->idOrFail(), $key);

        return $this->show($key);
    }

    public function enable(ManageModulesRequest $request, string $key, EnableModuleAction $action): ModuleResource
    {
        $action->handle($this->tenant->idOrFail(), $key);

        return $this->show($key);
    }

    public function disable(ManageModulesRequest $request, string $key, DisableModuleAction $action): ModuleResource
    {
        $action->handle($this->tenant->idOrFail(), $key);

        return $this->show($key);
    }

    private function show(string $key): ModuleResource
    {
        return new ModuleResource([
            'module' => $this->registry->get($key),
            'state' => $this->access->state($key),
            'row' => TenantModule::query()->where('module_key', $key)->first(),
        ]);
    }
}
