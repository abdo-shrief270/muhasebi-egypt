<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Branch;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use Illuminate\Support\Facades\DB;

/**
 * A second (or later) branch needs the multi-branch module — extra branches are a paid add-on.
 */
final class SaveBranchAction
{
    public function __construct(
        private readonly ModuleAccess $modules,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{name?: string, address?: string|null, phone?: string|null, is_active?: bool}  $data
     */
    public function handle(string $tenantId, array $data, ?Branch $branch = null): Branch
    {
        $creating = $branch === null;

        if ($creating && Branch::query()->exists() && ! $this->modules->enabled('multi_branch', $tenantId)) {
            throw new DomainRuleException('لإضافة فرع تاني فعّل قسم «الفروع المتعددة».', 'multi_branch_required', 403, ['module' => 'multi_branch']);
        }

        if ($branch?->is_main && ($data['is_active'] ?? true) === false) {
            throw new DomainRuleException('مينفعش توقف الفرع الرئيسي.', 'main_branch_immutable');
        }

        return DB::transaction(function () use ($tenantId, $data, $branch, $creating): Branch {
            $branch ??= new Branch([
                'tenant_id' => $tenantId,
                'is_main' => false,
                'is_active' => true,
                'invoice_prefix' => 'B'.(Branch::query()->count() + 1),
            ]);

            $branch->fill(array_intersect_key($data, array_flip(['name', 'address', 'phone', 'is_active'])))->save();

            $this->audit->record(
                $creating ? 'branches.created' : 'branches.updated',
                ($creating ? 'أضاف' : 'عدّل')." الفرع «{$branch->name}»",
                $branch,
            );

            return $branch;
        });
    }
}
