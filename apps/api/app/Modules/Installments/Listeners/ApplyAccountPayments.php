<?php

declare(strict_types=1);

namespace App\Modules\Installments\Listeners;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Customers\Events\CustomerPaid;
use App\Modules\Installments\Enums\PlanStatus;
use App\Modules\Installments\Models\InstallmentItem;
use App\Modules\Installments\Models\InstallmentPlan;
use App\Modules\Installments\Support\PlanBook;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use Illuminate\Support\Facades\DB;

/**
 * «تحصيل» from the customer page goes to the customer's ordinary آجل first; once their balance is
 * below what their active plans still account for, the difference is applied to the plans, the
 * installment due soonest first. Payments collected on a plan (source = installments) are already
 * applied.
 */
final class ApplyAccountPayments extends ModuleListener
{
    public function __construct(
        private readonly CustomerAccounts $customers,
        private readonly PlanBook $book,
    ) {}

    protected function module(): string
    {
        return 'installments';
    }

    protected function shouldReact(DomainEvent $event): bool
    {
        return $event instanceof CustomerPaid && $event->source === null;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerPaid);
        $planIds = InstallmentPlan::query()
            ->where('customer_id', $event->customerId)
            ->where('status', PlanStatus::Active)
            ->orderBy('number')
            ->lockForUpdate()
            ->pluck('id');
        if ($planIds->isEmpty()) {
            return;
        }

        $balance = $this->customers->find($event->customerId)?->balance ?? 0;
        $excess = min($event->amount, $this->book->outstandingFor($event->customerId) - max(0, $balance));
        if ($excess <= 0) {
            return;
        }

        // The plans whose next unpaid installment is due soonest come first.
        $order = InstallmentItem::query()
            ->whereIn('plan_id', $planIds)
            ->where('paid', '<', DB::raw('amount'))
            ->groupBy('plan_id')
            ->orderByRaw('min(due_on)')
            ->pluck('plan_id');
        foreach ($order as $planId) {
            $plan = InstallmentPlan::query()->find($planId);
            $excess -= $this->book->apply($plan, $excess, 'account', $event->branchId, $event->paymentMethod);
            if ($excess <= 0) {
                return;
            }
        }
    }
}
