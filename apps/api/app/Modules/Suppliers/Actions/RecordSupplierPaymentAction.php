<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Actions;

use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Modules\Suppliers\Events\SupplierPaid;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierTransaction;
use App\Modules\Suppliers\Support\SupplierAccount;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Money paid to a supplier against the account. Paying more than is owed is allowed
 * (an advance): the balance goes below zero, meaning the supplier owes the shop.
 */
final class RecordSupplierPaymentAction
{
    public function __construct(
        private readonly SupplierAccount $account,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $tenantId, string $branchId, Supplier $supplier, int $amount, PaymentMethod $method, ?string $note): SupplierTransaction
    {
        return DB::transaction(function () use ($tenantId, $branchId, $supplier, $amount, $method, $note): SupplierTransaction {
            $transaction = $this->account->post($supplier->id, SupplierTransactionType::Payment, -$amount, method: $method, note: $note);

            $this->events->record(new SupplierPaid($tenantId, $supplier->id, $branchId, $amount, $method->value));
            $this->audit->record(
                'suppliers.paid',
                "دفع للمورد «{$supplier->name}» ".number_format($amount / 100, 2)." ج ({$method->label()})",
                $supplier,
                ['amount' => $amount, 'method' => $method->value],
            );

            return $transaction;
        });
    }
}
