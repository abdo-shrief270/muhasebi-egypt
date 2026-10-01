<?php

declare(strict_types=1);

namespace App\Modules\Installments\Actions;

use App\Modules\Installments\Enums\PlanStatus;
use App\Modules\Installments\Models\InstallmentPlan;
use App\Modules\Installments\Support\PlanBook;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Stops a plan's schedule. What's left stays on the customer's account as ordinary آجل (the
 * markup included: it was agreed); the shop can collect it or make a new plan for it.
 */
final class CancelPlanAction
{
    public function __construct(
        private readonly PlanBook $book,
        private readonly Auditor $audit,
    ) {}

    public function handle(InstallmentPlan $plan, Authenticatable $user, ?string $reason): InstallmentPlan
    {
        return DB::transaction(function () use ($plan, $user, $reason): InstallmentPlan {
            $locked = $this->book->lock($plan->id) ?? throw new DomainRuleException('التقسيط مش موجود.', 'plan_not_found', 404);
            if ($locked->status !== PlanStatus::Active) {
                throw new DomainRuleException('التقسيط ده مش شغّال.', 'plan_not_active');
            }

            $locked->status = PlanStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->cancelled_by_name = (string) $user->getAttribute('name');
            $locked->save();

            $this->audit->record(
                'installments.cancelled',
                "لغى التقسيط {$locked->reference()} لـ «{$locked->customer_name}» والباقي ".number_format($locked->remaining() / 100, 2).' ج فضل آجل على حسابه'.($reason ? " — {$reason}" : ''),
                $locked,
                ['remaining' => $locked->remaining(), 'reason' => $reason],
            );

            return $locked;
        });
    }
}
