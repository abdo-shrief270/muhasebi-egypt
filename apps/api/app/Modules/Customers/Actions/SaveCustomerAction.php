<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerLedger;
use App\Support\Audit\Auditor;
use Illuminate\Support\Facades\DB;

final class SaveCustomerAction
{
    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{name?: string, phone?: string|null, notes?: string|null, credit_limit?: int|null, is_active?: bool}  $data
     * @param  int  $openingBalance  piasters the customer already owed before the system (new customers only)
     */
    public function handle(string $tenantId, array $data, ?Customer $customer = null, int $openingBalance = 0): Customer
    {
        $creating = $customer === null;

        return DB::transaction(function () use ($tenantId, $data, $customer, $creating, $openingBalance): Customer {
            $customer ??= new Customer(['tenant_id' => $tenantId]);
            $limitBefore = $customer->credit_limit;
            $customer->fill($data)->save();

            if ($creating && $openingBalance !== 0) {
                $this->ledger->post($customer, CustomerTransactionType::Opening, $openingBalance, note: 'رصيد قبل استخدام السيستم');
            }

            $properties = array_filter([
                'opening_balance' => $openingBalance ?: null,
                'credit_limit' => ! $creating && $limitBefore !== $customer->credit_limit ? [$limitBefore, $customer->credit_limit] : null,
            ]);
            if ($creating || $properties !== []) {
                $this->audit->record(
                    $creating ? 'customers.created' : 'customers.credit_limit_changed',
                    $creating ? "أضاف العميل «{$customer->name}»" : "غيّر حد الآجل للعميل «{$customer->name}»",
                    $customer,
                    $properties,
                );
            }

            return $customer->refresh();
        });
    }
}
