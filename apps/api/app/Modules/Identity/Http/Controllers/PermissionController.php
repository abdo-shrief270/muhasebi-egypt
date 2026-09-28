<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\PermissionResolver;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

/**
 * Permissions the shop can grant (only from modules it uses), grouped by module for the roles screen.
 */
final class PermissionController
{
    public function index(ModuleRegistry $registry, PermissionResolver $resolver, CurrentTenant $tenant): JsonResponse
    {
        $grantable = $resolver->grantable($tenant->idOrFail());
        $groups = [];

        foreach ($registry->all() as $module) {
            $permissions = array_filter($module->permissions, fn (string $key): bool => in_array($key, $grantable, true), ARRAY_FILTER_USE_KEY);

            if ($permissions !== []) {
                $groups[] = [
                    'module' => $module->key,
                    'name' => $module->name,
                    'permissions' => array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($permissions), $permissions),
                ];
            }
        }

        return response()->json(['data' => $groups]);
    }
}
