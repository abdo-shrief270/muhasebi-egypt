<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\DomainRuleException;

final class BranchAccess
{
    /**
     * Active branches the user may work in, main branch first.
     *
     * @return list<string>
     */
    public function branchIdsFor(User $user): array
    {
        $query = Branch::withoutTenancy()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('created_at');

        if (! $user->is_owner) {
            $query->whereIn('id', $user->branches()->select('branches.id'));
        }

        return $query->pluck('id')->all();
    }

    /**
     * The branch to work in: the requested one if allowed (403 otherwise), else the user's default.
     * Null when the user has no branch at all.
     */
    public function resolve(User $user, ?string $requested): ?string
    {
        $allowed = $this->branchIdsFor($user);

        if ($requested !== null && $requested !== '' && ! in_array($requested, $allowed, true)) {
            throw new DomainRuleException('مش مسموح لك تشتغل في الفرع ده.', 'branch_forbidden', 403);
        }

        return $requested ?: ($allowed[0] ?? null);
    }
}
