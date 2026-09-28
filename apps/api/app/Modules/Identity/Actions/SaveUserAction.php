<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Adds an employee or updates one. The owner's role/branches/active flag can't be changed here.
 */
final class SaveUserAction
{
    public function __construct(private readonly Auditor $audit) {}

    /**
     * @param  array{name?: string, phone?: string, email?: string|null, password?: string|null, role_id?: int|null, branch_ids?: list<string>, is_active?: bool}  $data
     */
    public function handle(string $tenantId, array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($tenantId, $data, $user): User {
            $creating = $user === null;
            $user ??= new User(['tenant_id' => $tenantId, 'is_owner' => false, 'is_active' => true]);

            if ($user->is_owner && (array_key_exists('role_id', $data) || array_key_exists('branch_ids', $data) || ($data['is_active'] ?? true) === false)) {
                throw new DomainRuleException('صاحب المحل ليه كل الصلاحيات في كل الفروع، ومينفعش يتوقف.', 'owner_immutable');
            }

            if (array_key_exists('role_id', $data) && $data['role_id'] !== null && ! Role::query()->whereKey($data['role_id'])->exists()) {
                throw new DomainRuleException('الدور ده مش موجود.', 'role_not_found');
            }

            $wasActive = $user->exists ? $user->is_active : true;
            $user->fill(array_filter([
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'role_id' => $data['role_id'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($value): bool => $value !== null));

            if (array_key_exists('email', $data)) {
                $user->email = $data['email'];
            }
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            if (array_key_exists('branch_ids', $data)) {
                $branchIds = Branch::query()->whereIn('id', $data['branch_ids'])->pluck('id')->all();

                if ($branchIds === []) {
                    throw new DomainRuleException('لازم تختار فرع واحد على الأقل.', 'branches_required');
                }

                $user->branches()->sync($branchIds);
            }

            if ($wasActive && ! $user->is_active) {
                $user->tokens()->delete();
            }

            $this->audit->record(
                $creating ? 'users.created' : 'users.updated',
                match (true) {
                    $creating => "أضاف الموظف «{$user->name}»",
                    $wasActive && ! $user->is_active => "أوقف حساب «{$user->name}»",
                    ! $wasActive && $user->is_active => "رجّع حساب «{$user->name}»",
                    default => "عدّل بيانات «{$user->name}»",
                },
                $user,
                ['role_id' => $user->role_id, 'is_active' => $user->is_active],
            );

            return $user;
        });
    }
}
