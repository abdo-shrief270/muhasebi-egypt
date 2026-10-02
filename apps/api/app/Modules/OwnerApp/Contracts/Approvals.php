<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Contracts;

use App\Support\Exceptions\DomainRuleException;

/**
 * «اطلب موافقة»: actions that go past one of the owner's limits need an approval, from the
 * owner's phone or a manager's PIN at the counter. Call inside the action, before saving: the
 * approval (sent back by the app in the X-Approval-Id header) is used up in the caller's transaction.
 * Users who may approve (owner_app.approve) never need one.
 */
interface Approvals
{
    /** Whether the signed-in user needs an approval for this kind at this measure. */
    public function needed(ApprovalKind $kind, int $measure = 1): bool;

    /**
     * Uses up the approval for exactly this action, or throws `approval_required` (409) with a
     * signed request the app sends on to be approved. One action asks once, for every reason it
     * has (e.g. a big discount that also sells below cost). $payload identifies the action (same
     * data = same approval). Returns true when an approval was used, false when none was needed.
     *
     * @param  list<array{0: ApprovalKind, 1: int, 2: string}>  $reasons  [kind, measure, summary]
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainRuleException approval_required
     */
    public function require(array $reasons, array $payload, int $amount = 0, ?string $branchId = null): bool;
}
