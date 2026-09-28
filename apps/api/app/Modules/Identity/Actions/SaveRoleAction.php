<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Role;
use App\Support\Audit\Auditor;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;

final class SaveRoleAction
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  list<string>  $permissions
     */
    public function handle(string $tenantId, string $name, array $permissions, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($tenantId, $name, $permissions, $role): Role {
            $creating = $role === null;
            $known = array_keys($this->registry->permissions());

            $role ??= new Role(['tenant_id' => $tenantId]);
            $role->fill([
                'name' => $name,
                'permissions' => array_values(array_unique(array_intersect($permissions, $known))),
            ])->save();

            $this->audit->record(
                $creating ? 'roles.created' : 'roles.updated',
                ($creating ? 'أضاف' : 'عدّل')." الدور «{$role->name}» (".count($role->permissions).' صلاحية)',
                $role,
            );

            return $role;
        });
    }
}
