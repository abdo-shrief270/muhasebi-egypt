<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Customers\Contracts\CustomerSummary;
use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerLedger;
use App\Support\Exceptions\DomainRuleException;

final class CustomerAccountsService implements CustomerAccounts
{
    public function __construct(private readonly CustomerLedger $ledger) {}

    public function find(string $customerId): ?CustomerSummary
    {
        return Customer::query()->find($customerId)?->summary();
    }

    public function chargeSale(string $customerId, int $amount, string $saleId, string $reference, string $branchId): void
    {
        $customer = $this->ledger->lock($customerId) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);
        if (! $customer->is_active) {
            throw new DomainRuleException("حساب «{$customer->name}» موقوف.", 'customer_inactive');
        }
        if ($customer->credit_limit !== null && $customer->balance + $amount > $customer->credit_limit) {
            $available = max(0, $customer->credit_limit - $customer->balance);
            throw new DomainRuleException(
                "الآجل كده هيعدّي حد «{$customer->name}». المتاح ".number_format($available / 100, 2).' ج.',
                'credit_limit_exceeded',
                context: ['available' => $available, 'balance' => $customer->balance, 'credit_limit' => $customer->credit_limit],
            );
        }

        $this->ledger->post($customer, CustomerTransactionType::Sale, $amount, $branchId, 'sale', $saleId, $reference);
    }

    public function creditReturn(string $customerId, int $amount, string $returnId, string $reference, string $branchId): void
    {
        $customer = $this->ledger->lock($customerId) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);

        $this->ledger->post($customer, CustomerTransactionType::SaleReturn, -$amount, $branchId, 'sale_return', $returnId, $reference);
    }

    public function touch(string $customerId): void
    {
        Customer::query()->whereKey($customerId)->update(['last_activity_at' => now()]);
    }
}
