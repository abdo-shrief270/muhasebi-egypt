<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp;

use App\Modules\OwnerApp\Contracts\ApprovalKind;
use App\Modules\OwnerApp\Contracts\Approvals;
use App\Modules\OwnerApp\Models\ApprovalRequest;
use App\Modules\OwnerApp\Support\ApprovalToken;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;

final class ApprovalsService implements Approvals
{
    public function __construct(
        private readonly FeatureAccess $features,
        private readonly Auth $auth,
        private readonly Request $request,
    ) {}

    public function needed(ApprovalKind $kind, int $measure = 1): bool
    {
        $user = $this->auth->guard('sanctum')->user();
        if ($user === null || $user->can('owner_app.approve') || ! $this->features->enabled($kind->feature())) {
            return false;
        }
        $limit = $this->features->setting($kind->feature());
        if ($limit === null) {
            return $measure > 0;
        }
        // Percent limits are in %, money limits in pounds; measures are percent / piasters.
        $threshold = $kind === ApprovalKind::Discount ? (int) $limit : (int) $limit * 100;

        return $measure > $threshold;
    }

    public function require(array $reasons, array $payload, int $amount = 0, ?string $branchId = null): bool
    {
        $reasons = array_values(array_filter($reasons, fn (array $r) => $this->needed($r[0], $r[1])));
        if ($reasons === []) {
            return false;
        }

        $user = $this->auth->guard('sanctum')->user();
        $kind = $reasons[0][0];
        $summary = implode(' + ', array_column($reasons, 2));
        $hash = ApprovalToken::hash($kind, [...$payload, '_reasons' => array_map(fn (array $r) => $r[0]->value, $reasons)]);
        $id = (string) $this->request->header('X-Approval-Id', '');
        if ($id !== '') {
            $approval = ApprovalRequest::query()->lockForUpdate()->find($id);
            if ($approval !== null
                && $approval->status === 'approved'
                && $approval->used_at === null
                && hash_equals($approval->payload_hash, $hash)
                && $approval->requested_by === $user->getAuthIdentifier()
                && $approval->expires_at->isFuture()) {
                $approval->used_at = now();
                $approval->save();

                return true;
            }
        }

        throw new DomainRuleException(
            "محتاج موافقة: {$summary}",
            'approval_required',
            409,
            ['approval' => [
                'kind' => $kind->value,
                'kind_label' => $kind->label(),
                'summary' => $summary,
                'amount' => $amount,
                'token' => ApprovalToken::issue($kind, $hash, $summary, $amount, $branchId, (string) $user->getAuthIdentifier()),
            ]],
        );
    }
}
