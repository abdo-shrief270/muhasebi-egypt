<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Hash;

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

    public function matchPin(string $permission, string $pin): ?array
    {
        $user = User::query()
            ->where('tenant_id', $this->tenant->idOrFail())
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->get()
            ->first(fn (User $user) => Hash::check($pin, (string) $user->pin_hash) && $this->permissions->allows($user, $permission));

        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
