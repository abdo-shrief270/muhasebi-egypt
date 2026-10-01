<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Enums\PaymentMethod;
use App\Modules\Customers\Events\CustomerPaid;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerTransaction;
use App\Modules\Customers\Support\CustomerLedger;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Money collected from a customer against their account. It goes into the collector's drawer, so
 * cash needs an open shift. Paying more than owed leaves store credit (a negative balance).
 */
final class RecordCustomerPaymentAction
{
    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly CashDrawer $drawer,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $tenantId, string $branchId, Customer $customer, int $amount, PaymentMethod $method, ?string $note, ?string $source = null): CustomerTransaction
    {
        return DB::transaction(function () use ($tenantId, $branchId, $customer, $amount, $method, $note, $source): CustomerTransaction {
            $locked = $this->ledger->lock($customer->id) ?? $customer;
            $transaction = $this->ledger->post($locked, CustomerTransactionType::Payment, -$amount, $branchId, method: $method, note: $note);

            $this->drawer->record($branchId, DrawerEntry::CustomerPayment, $method->value, $amount, 'customer_transaction', $transaction->id, "تحصيل من {$locked->name}");
            $this->events->record(new CustomerPaid($tenantId, $locked->id, $branchId, $amount, $method->value, $source));
            $this->audit->record(
                'customers.paid',
                "حصّل من العميل «{$locked->name}» ".number_format($amount / 100, 2)." ج ({$method->label()})",
                $locked,
                ['amount' => $amount, 'method' => $method->value, 'balance_after' => $locked->balance],
            );

            return $transaction;
        });
    }
}
