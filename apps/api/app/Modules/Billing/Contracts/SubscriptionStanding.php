<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/** Whether shops are in good standing, for features that only paying (or trialling) shops get. */
interface SubscriptionStanding
{
    /**
     * Shown in the marketplace: on trial, paid, or in the week of grace after the end; not once
     * restricted or suspended.
     */
    public function listed(string $tenantId): bool;

    /** @return list<string> every shop that is listed now */
    public function listedTenants(): array;

    /** @return string the status key (trialing / active / past_due / restricted / suspended), none = no subscription */
    public function status(string $tenantId): string;
}
