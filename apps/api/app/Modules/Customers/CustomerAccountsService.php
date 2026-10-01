<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Actions\RecordCustomerPaymentAction;
use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Customers\Contracts\CustomerSummary;
use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Enums\PaymentMethod;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerLedger;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Factory as Auth;
use Propaganistas\LaravelPhone\PhoneNumber;

final class CustomerAccountsService implements CustomerAccounts
{
    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly CurrentTenant $tenant,
        private readonly Auth $auth,
        private readonly RecordCustomerPaymentAction $payments,
    ) {}

    public function find(string $customerId): ?CustomerSummary
    {
        return Customer::query()->find($customerId)?->summary();
    }

    public function chargeSale(string $customerId, int $amount, string $saleId, string $reference, string $branchId): void
    {
        $this->charge($customerId, $amount, CustomerTransactionType::Sale, 'sale', $saleId, $reference, $branchId);
    }

    public function chargeRepair(string $customerId, int $amount, string $ticketId, string $reference, string $branchId): void
    {
        $this->charge($customerId, $amount, CustomerTransactionType::Repair, 'repair_ticket', $ticketId, $reference, $branchId);
    }

    public function findOrCreate(string $name, string $phone, ?bool $consent = null): CustomerSummary
    {
        try {
            $e164 = (new PhoneNumber($phone, 'EG'))->formatE164();
        } catch (\Throwable) {
            throw new DomainRuleException('رقم موبايل العميل مش صحيح.', 'customer_phone_invalid');
        }

        $customer = Customer::query()->where('phone', $e164)->first()
            ?? new Customer(['tenant_id' => $this->tenant->idOrFail(), 'name' => trim($name) !== '' ? trim($name) : $e164, 'phone' => $e164]);
        if ($consent !== null && ($customer->data_consent === null || ! $customer->exists)) {
            $customer->recordConsent($consent, $this->auth->guard('sanctum')->user());
        }
        $customer->save();

        return $customer->summary();
    }

    private function charge(string $customerId, int $amount, CustomerTransactionType $type, string $refType, string $refId, string $reference, string $branchId): void
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

        $this->ledger->post($customer, $type, $amount, $branchId, $refType, $refId, $reference);
    }

    public function creditReturn(string $customerId, int $amount, string $returnId, string $reference, string $branchId): void
    {
        $customer = $this->ledger->lock($customerId) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);

        $this->ledger->post($customer, CustomerTransactionType::SaleReturn, -$amount, $branchId, 'sale_return', $returnId, $reference);
    }

    public function chargeInstallmentMarkup(string $customerId, int $amount, string $planId, string $reference, string $branchId): void
    {
        $customer = $this->ledger->lock($customerId) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);

        $this->ledger->post($customer, CustomerTransactionType::InstallmentMarkup, $amount, $branchId, 'installment_plan', $planId, $reference);
    }

    public function collect(string $customerId, int $amount, string $method, string $branchId, ?string $note = null, ?string $source = null): string
    {
        $customer = Customer::query()->find($customerId) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);

        return $this->payments->handle($this->tenant->idOrFail(), $branchId, $customer, $amount, PaymentMethod::from($method), $note, $source)->id;
    }

    public function touch(string $customerId): void
    {
        Customer::query()->whereKey($customerId)->update(['last_activity_at' => now()]);
    }
}
