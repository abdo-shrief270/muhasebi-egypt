<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/** How sure we are it's really the signed-in user (for approvals and other sensitive answers). */
interface AccountSecurity
{
    /** This device was unlocked with the user's fingerprint / face (passkey) or PIN in the last few minutes. */
    public function recentlyVerified(): bool;

    /** The signed-in user (or the given one) has two-factor sign-in on. */
    public function twoFactorEnabled(?string $userId = null): bool;
}
