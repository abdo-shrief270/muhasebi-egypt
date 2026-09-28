<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;

/**
 * What a user may do: the owner may do everything, others what their role grants —
 * and in both cases only for modules the shop can currently use.
 */
final class PermissionResolver
{
    /** @var array<string, list<string>> */
    private array $memo = [];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleAccess $modules,
    ) {}

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        return $this->memo[$user->id] ??= $this->resolve($user);
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user), true);
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * Permissions the shop could grant right now (from usable modules).
     *
     * @return list<string>
     */
    public function grantable(string $tenantId): array
    {
        return array_keys(array_filter(
            $this->registry->permissions(),
            fn (array $permission): bool => $this->modules->enabled($permission['module'], $tenantId),
        ));
    }

    /**
     * @return list<string>
     */
    private function resolve(User $user): array
    {
        if (! $user->is_active) {
            return [];
        }

        $grantable = $this->grantable($user->tenant_id);

        if ($user->is_owner) {
            return $grantable;
        }

        $role = $user->role_id === null ? null : Role::withoutTenancy()->find($user->role_id);

        return array_values(array_intersect($grantable, $role?->permissions ?? []));
    }
}
