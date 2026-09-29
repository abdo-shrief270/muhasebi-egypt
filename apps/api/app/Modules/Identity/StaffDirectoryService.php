<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;

final class StaffDirectoryService implements StaffDirectory
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly CurrentTenant $tenant,
    ) {}

    public function withPermission(string $permission): array
    {
        // Users aren't tenant scoped (they're resolved before the shop is known): filter by hand.
        return User::query()
            ->where('tenant_id', $this->tenant->idOrFail())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $this->permissions->allows($user, $permission))
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name])
            ->all();
    }
}
