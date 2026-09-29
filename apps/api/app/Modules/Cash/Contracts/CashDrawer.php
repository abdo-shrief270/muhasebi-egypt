<?php

declare(strict_types=1);

namespace App\Modules\Cash\Contracts;

use App\Support\Exceptions\DomainRuleException;

/**
 * Records money taken or paid out by the signed-in user in a branch, on their open shift.
 * Call inside the caller's transaction.
 */
interface CashDrawer
{
    /**
     * Cash always needs an open shift (it goes into a physical drawer); other methods join the
     * open shift when there is one, so the close shows them too. $requireShift makes any method
     * need one (selling at the POS).
     *
     * @param  string  $method  cash | card | wallet | instapay
     * @param  int  $amount  piasters; + in, - out
     *
     * @throws DomainRuleException shift_not_open (409)
     */
    public function record(
        string $branchId,
        DrawerEntry $entry,
        string $method,
        int $amount,
        string $refType,
        string $refId,
        ?string $note = null,
        bool $requireShift = false,
    ): void;

    /** Whether the signed-in user has an open shift in the branch. */
    public function hasOpenShift(string $branchId): bool;
}
