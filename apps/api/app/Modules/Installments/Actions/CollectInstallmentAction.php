<?php

declare(strict_types=1);

namespace App\Modules\Installments\Actions;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Installments\Enums\PlanStatus;
use App\Modules\Installments\Models\InstallmentPlan;
use App\Modules\Installments\Support\PlanBook;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * An installment paid at the counter: «تحصيل» on the customer's account (the drawer, the ledger,
 * the audit entry) and the amount applied to the plan, oldest installment first.
 */
final class CollectInstallmentAction
{
    public function __construct(
        private readonly CustomerAccounts $customers,
        private readonly PlanBook $book,
    ) {}

    public function handle(InstallmentPlan $plan, string $branchId, int $amount, string $method): InstallmentPlan
    {
        return DB::transaction(function () use ($plan, $branchId, $amount, $method): InstallmentPlan {
            $locked = $this->book->lock($plan->id) ?? throw new DomainRuleException('التقسيط مش موجود.', 'plan_not_found', 404);
            if ($locked->status !== PlanStatus::Active) {
                throw new DomainRuleException('التقسيط ده مش شغّال.', 'plan_not_active');
            }
            if ($amount > $locked->remaining()) {
                throw new DomainRuleException(
                    'المبلغ أكبر من الباقي من التقسيط ('.number_format($locked->remaining() / 100, 2).' ج).',
                    'amount_over_remaining',
                    context: ['remaining' => $locked->remaining()],
                );
            }

            $transactionId = $this->customers->collect(
                $locked->customer_id, $amount, $method, $branchId, "قسط {$locked->reference()}", source: 'installments',
            );
            $this->book->apply($locked, $amount, 'counter', $branchId, $method, $transactionId);

            return $locked->refresh();
        });
    }
}
