<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Http\Resources;

use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{module: ModuleManifest, state: ModuleState, row: TenantModule|null} $resource
 */
final class ModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['module' => $module, 'state' => $state, 'row' => $row] = $this->resource;

        return [
            'key' => $module->key,
            'name' => $module->name,
            'description' => $module->description,
            'tier' => $module->tier->value,
            'depends_on' => $module->dependsOn,
            'state' => $state->value,
            'state_label' => $state->label(),
            'available' => $module->available,
            'usable' => $module->available && $state->isUsable(),
            'entitled' => $module->isOptional() ? (bool) $row?->entitled : true,
            'trial_available' => $module->isOptional() && $module->available && $row?->trial_started_at === null && ! $state->isUsable(),
            'trial_ends_at' => $row?->trial_ends_at?->toIso8601String(),
            'menu' => array_map(fn (MenuItem $item): array => $item->toArray(), array_values(array_filter($module->menu, fn (MenuItem $item) => $item->ready))),
        ];
    }
}
