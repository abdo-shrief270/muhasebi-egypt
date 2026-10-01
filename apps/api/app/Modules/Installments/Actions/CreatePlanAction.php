<?php

declare(strict_types=1);

namespace App\Modules\Installments\Actions;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Installments\Enums\PlanStatus;
use App\Modules\Installments\Models\InstallmentItem;
use App\Modules\Installments\Models\InstallmentPlan;
use App\Modules\Installments\Support\PlanBook;
use App\Modules\Installments\Support\Schedule;
use App\Modules\Sales\Contracts\CustomerSales;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use App\Support\Numbering\DocumentNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Splits what a customer owes — a credit sale's آجل, or part of their account — into dated
 * installments. The principal is already on the account; the markup is added to it here. A plan
 * never covers more than the customer owes outside their other active plans.
 */
final class CreatePlanAction
{
    public function __construct(
        private readonly CustomerAccounts $customers,
        private readonly CustomerSales $sales,
        private readonly PlanBook $book,
        private readonly DocumentNumbers $numbers,
        private readonly FeatureAccess $features,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{customer_id: string, sale_id?: string|null, principal: int, markup?: int, markup_rate?: int|null, count: int, interval_months?: int, first_due_on: string, guarantor_name?: string|null, guarantor_phone?: string|null, notes?: string|null}  $data
     */
    public function handle(string $tenantId, string $branchId, Authenticatable $user, array $data): InstallmentPlan
    {
        $principal = (int) $data['principal'];
        $markup = (int) ($data['markup'] ?? 0);
        $count = (int) $data['count'];
        $interval = (int) ($data['interval_months'] ?? 1);
        if ($this->features->enabled('installments.guarantor_required') && (empty($data['guarantor_name']) || empty($data['guarantor_phone']))) {
            throw new DomainRuleException('اكتب اسم الضامن ورقم موبايله.', 'guarantor_required');
        }

        return DB::transaction(function () use ($tenantId, $branchId, $user, $data, $principal, $markup, $count, $interval): InstallmentPlan {
            $customer = $this->customers->find($data['customer_id']) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);
            if (! $customer->isActive) {
                throw new DomainRuleException("حساب «{$customer->name}» موقوف.", 'customer_inactive');
            }

            $sale = null;
            if (! empty($data['sale_id'])) {
                $sale = $this->sales->creditOf($data['sale_id']) ?? throw new DomainRuleException('الفاتورة مش موجودة.', 'sale_not_found', 404);
                if ($sale['customer_id'] !== $customer->id) {
                    throw new DomainRuleException('الفاتورة دي مش على العميل ده.', 'sale_other_customer');
                }
                $planned = (int) InstallmentPlan::query()->where('sale_id', $data['sale_id'])->where('status', '!=', PlanStatus::Cancelled)->sum('principal');
                if ($principal > $sale['credit'] - $planned) {
                    throw new DomainRuleException('المبلغ أكبر من الآجل اللي على الفاتورة.', 'principal_over_sale', context: ['available' => max(0, $sale['credit'] - $planned)]);
                }
            }

            $free = $customer->balance - $this->book->outstandingFor($customer->id);
            if ($principal > $free) {
                throw new DomainRuleException(
                    'المبلغ أكبر من اللي على العميل برّه التقسيط. المتاح '.number_format(max(0, $free) / 100, 2).' ج.',
                    'principal_over_balance',
                    context: ['available' => max(0, $free)],
                );
            }

            $plan = InstallmentPlan::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'number' => $this->numbers->next($tenantId, 'installment_plan'),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'sale_id' => $data['sale_id'] ?? null,
                'sale_reference' => $sale['reference'] ?? null,
                'principal' => $principal,
                'markup' => $markup,
                'markup_rate' => $data['markup_rate'] ?? null,
                'total' => $principal + $markup,
                'count' => $count,
                'interval_months' => $interval,
                'first_due_on' => $data['first_due_on'],
                'guarantor_name' => $data['guarantor_name'] ?? null,
                'guarantor_phone' => $data['guarantor_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => PlanStatus::Active,
                'created_by' => $user->getAuthIdentifier(),
                'created_by_name' => (string) $user->getAttribute('name'),
            ]);

            foreach (Schedule::make($plan->total, $count, CarbonImmutable::parse($data['first_due_on']), $interval) as $item) {
                InstallmentItem::create(['tenant_id' => $tenantId, 'plan_id' => $plan->id, ...$item]);
            }

            if ($markup > 0) {
                $this->customers->chargeInstallmentMarkup($customer->id, $markup, $plan->id, $plan->reference(), $branchId);
            }

            $this->audit->record(
                'installments.created',
                'قسّط '.number_format($plan->total / 100, 2)." ج على «{$customer->name}» ({$count} قسط) — {$plan->reference()}",
                $plan,
                ['principal' => $principal, 'markup' => $markup, 'count' => $count, 'sale' => $plan->sale_reference],
            );

            return $plan;
        });
    }
}
