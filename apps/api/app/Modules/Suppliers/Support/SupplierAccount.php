<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Support;

use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierTransaction;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts to a supplier's account under a row lock, keeping the balance and the statement in step.
 * Call inside the caller's transaction.
 */
final class SupplierAccount
{
    public function __construct(private readonly Auth $auth) {}

    /**
     * @param  int  $amount  piasters; + we owe more (a purchase), - we owe less (a payment or a return)
     */
    public function post(
        string $supplierId,
        SupplierTransactionType $type,
        int $amount,
        ?Model $reference = null,
        ?PaymentMethod $method = null,
        ?string $note = null,
        ?string $refType = null,
        ?string $refId = null,
    ): SupplierTransaction {
        $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
        $supplier->balance += $amount;
        $supplier->save();

        $user = $this->auth->guard('sanctum')->user();

        return SupplierTransaction::create([
            'tenant_id' => $supplier->tenant_id,
            'supplier_id' => $supplier->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $supplier->balance,
            'payment_method' => $method,
            'ref_type' => $refType ?? ($reference ? strtolower(class_basename($reference)) : null),
            'ref_id' => $refId ?? $reference?->getKey(),
            'note' => $note,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
