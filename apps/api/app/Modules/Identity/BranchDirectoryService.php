<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class BranchDirectoryService implements BranchDirectory
{
    public function __construct(private readonly BranchAccess $access) {}

    public function accessibleBranches(Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [];
        }
        $ids = $this->access->branchIdsFor($user);
        $names = Branch::withoutTenancy()->whereIn('id', $ids)->pluck('name', 'id');

        $branches = [];
        foreach ($ids as $id) {
            $branches[$id] = (string) $names[$id];
        }

        return $branches;
    }

    public function mainBranchId(): ?string
    {
        return Branch::query()->where('is_main', true)->value('id');
    }
}
