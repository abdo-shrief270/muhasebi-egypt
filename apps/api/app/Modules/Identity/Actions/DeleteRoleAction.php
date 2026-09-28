<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Role;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

final class DeleteRoleAction
{
    public function __construct(private readonly Auditor $audit) {}

    public function handle(Role $role): void
    {
        $count = $role->users()->count();

        if ($count > 0) {
            throw new DomainRuleException("فيه {$count} موظف على الدور ده. غيّر دورهم الأول.", 'role_in_use');
        }

        DB::transaction(function () use ($role): void {
            $this->audit->record('roles.deleted', "مسح الدور «{$role->name}»", $role);
            $role->delete();
        });
    }
}
