<?php

declare(strict_types=1);

namespace App\Modules\Customers\Support;

use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Enums\PaymentMethod;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerTransaction;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Posts to a customer's account under a row lock, keeping the balance and the statement in step.
 * Call inside the caller's transaction.
 */
final class CustomerLedger
{
    public function __construct(private readonly Auth $auth) {}

    public function lock(string $customerId): ?Customer
    {
        return Customer::query()->lockForUpdate()->find($customerId);
    }

    /**
     * @param  int  $amount  piasters; + owes more (a credit sale), - owes less (a payment or a return)
     */
    public function post(
        Customer $customer,
        CustomerTransactionType $type,
        int $amount,
        ?string $branchId = null,
        ?string $refType = null,
        ?string $refId = null,
        ?string $reference = null,
        ?PaymentMethod $method = null,
        ?string $note = null,
    ): CustomerTransaction {
        $customer->balance += $amount;
        $customer->last_activity_at = now();
        $customer->save();

        $user = $this->auth->guard('sanctum')->user();

        return CustomerTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'branch_id' => $branchId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $customer->balance,
            'payment_method' => $method,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'reference' => $reference,
            'note' => $note,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
