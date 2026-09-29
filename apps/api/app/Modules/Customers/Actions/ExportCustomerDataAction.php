<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerTransaction;
use App\Modules\Repairs\Contracts\CustomerRepairs;
use App\Modules\Sales\Contracts\CustomerSales;
use App\Support\Audit\Auditor;

/**
 * Everything the shop keeps about a customer, for handing to them (Personal Data Protection Law
 * 151/2020): profile, account statement, invoices and repair tickets. Money in piasters.
 */
final class ExportCustomerDataAction
{
    public function __construct(
        private readonly CustomerSales $sales,
        private readonly CustomerRepairs $repairs,
        private readonly Auditor $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Customer $customer): array
    {
        $data = [
            'exported_at' => now()->toIso8601String(),
            'money_unit' => 'piasters (1/100 EGP)',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'notes' => $customer->notes,
                'balance' => $customer->balance,
                'credit_limit' => $customer->credit_limit,
                'is_active' => $customer->is_active,
                'created_at' => $customer->created_at?->toIso8601String(),
                'last_activity_at' => $customer->last_activity_at?->toIso8601String(),
                'data_consent' => $customer->data_consent,
                'data_consent_at' => $customer->data_consent_at?->toIso8601String(),
                'data_consent_by' => $customer->data_consent_by_name,
                'erased_at' => $customer->erased_at?->toIso8601String(),
            ],
            'account_statement' => $customer->transactions()->orderBy('seq')->get()->map(fn (CustomerTransaction $t): array => [
                'type' => $t->type->label(),
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'payment_method' => $t->payment_method?->label(),
                'reference' => $t->reference,
                'note' => $t->note,
                'created_at' => $t->created_at->toIso8601String(),
            ])->all(),
            'sales' => $this->sales->forCustomer($customer->id),
            'repairs' => $this->repairs->forCustomer($customer->id),
        ];

        $this->audit->record('customers.exported', "نزّل بيانات العميل «{$customer->name}»", $customer);

        return $data;
    }
}
