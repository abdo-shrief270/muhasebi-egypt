<?php

declare(strict_types=1);

namespace App\Modules\Installments\Support;

use App\Modules\Installments\Enums\PlanStatus;
use App\Modules\Installments\Models\InstallmentPayment;
use App\Modules\Installments\Models\InstallmentPlan;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;

/**
 * Applies money to a plan: its installments in order, oldest first, then the plan's paid total and
 * status, plus an append-only payment row. Call inside a transaction; it locks the plan row.
 */
final class PlanBook
{
    public function __construct(private readonly Auth $auth) {}

    public function lock(string $planId): ?InstallmentPlan
    {
        return InstallmentPlan::query()->lockForUpdate()->find($planId);
    }

    /** Applies up to $amount (never more than the plan's remainder) and returns what was applied. */
    public function apply(InstallmentPlan $plan, int $amount, string $source, ?string $branchId = null, ?string $method = null, ?string $customerTransactionId = null): int
    {
        $amount = min($amount, $plan->remaining());
        if ($amount <= 0) {
            return 0;
        }

        $left = $amount;
        foreach ($plan->items()->where('paid', '<', DB::raw('amount'))->get() as $item) {
            $take = min($left, $item->remaining());
            $item->paid += $take;
            if ($item->remaining() === 0) {
                $item->paid_at = now();
            }
            $item->save();
            $left -= $take;
            if ($left === 0) {
                break;
            }
        }

        $plan->paid += $amount;
        if ($plan->remaining() === 0) {
            $plan->status = PlanStatus::Completed;
            $plan->completed_at = now();
        }
        $plan->save();

        $user = $this->auth->guard('sanctum')->user();
        InstallmentPayment::create([
            'tenant_id' => $plan->tenant_id,
            'plan_id' => $plan->id,
            'branch_id' => $branchId,
            'amount' => $amount,
            'method' => $method,
            'source' => $source,
            'customer_transaction_id' => $customerTransactionId,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);

        return $amount;
    }

    /** What the customer's active plans still account for on their balance. */
    public function outstandingFor(string $customerId, ?string $exceptPlanId = null): int
    {
        return (int) InstallmentPlan::query()
            ->where('customer_id', $customerId)
            ->where('status', PlanStatus::Active)
            ->when($exceptPlanId, fn ($q) => $q->whereKeyNot($exceptPlanId))
            ->sum(DB::raw('total - paid'));
    }
}
