<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/** The current shop's staff as other modules see them (to assign work, show names). */
interface StaffDirectory
{
    /**
     * Active staff (owner included) who hold the permission, by name.
     *
     * @return array<string, string> user id => name
     */
    public function withPermission(string $permission): array;

    /**
     * The active staff member with this permission whose approval PIN this is (a manager approving
     * at the counter), or null.
     *
     * @return array{id: string, name: string}|null
     */
    public function matchPin(string $permission, string $pin): ?array;
}
