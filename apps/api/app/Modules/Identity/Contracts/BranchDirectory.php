<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/** The shop's branches as other modules see them. */
interface BranchDirectory
{
    /**
     * Active branches the user may see (all of them for the owner), main branch first.
     *
     * @return array<string, string> id => name
     */
    public function accessibleBranches(Authenticatable $user): array;

    /** The current shop's main branch (where work that isn't tied to a branch lands). */
    public function mainBranchId(): ?string;
}
