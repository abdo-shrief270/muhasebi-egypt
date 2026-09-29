<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Actions;

use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Support\SupplierAccount;
use App\Support\Audit\Auditor;
use Illuminate\Support\Facades\DB;

final class SaveSupplierAction
{
    public function __construct(
        private readonly SupplierAccount $account,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{name?: string, phone?: string|null, notes?: string|null, is_active?: bool}  $data
     * @param  int  $openingBalance  piasters owed to the supplier before using the system (new suppliers only)
     */
    public function handle(string $tenantId, array $data, ?Supplier $supplier = null, int $openingBalance = 0): Supplier
    {
        $creating = $supplier === null;

        return DB::transaction(function () use ($tenantId, $data, $supplier, $creating, $openingBalance): Supplier {
            $supplier ??= new Supplier(['tenant_id' => $tenantId]);
            $supplier->fill($data)->save();

            if ($creating && $openingBalance !== 0) {
                $this->account->post($supplier->id, SupplierTransactionType::Opening, $openingBalance, note: 'رصيد قبل استخدام السيستم');
            }

            $this->audit->record(
                $creating ? 'suppliers.created' : 'suppliers.updated',
                ($creating ? 'أضاف' : 'عدّل')." المورد «{$supplier->name}»",
                $supplier,
                $openingBalance !== 0 ? ['opening_balance' => $openingBalance] : [],
            );

            return $supplier->refresh();
        });
    }
}
