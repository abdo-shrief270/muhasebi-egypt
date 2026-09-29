<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;

final class SaveCustomerAction
{
    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  array{name?: string, phone?: string|null, notes?: string|null, credit_limit?: int|null, is_active?: bool}  $data
     * @param  int  $openingBalance  piasters the customer already owed before the system (new customers only)
     * @param  bool|null  $consent  the customer agreed to having their data kept (null = not asked now)
     */
    public function handle(string $tenantId, array $data, ?Customer $customer = null, int $openingBalance = 0, ?bool $consent = null): Customer
    {
        $creating = $customer === null;
        if ($customer?->isErased()) {
            throw new DomainRuleException('بيانات العميل ده اتمسحت؛ مينفعش يتعدّل.', 'customer_erased');
        }

        return DB::transaction(function () use ($tenantId, $data, $customer, $creating, $openingBalance, $consent): Customer {
            $customer ??= new Customer(['tenant_id' => $tenantId]);
            $limitBefore = $customer->credit_limit;
            $customer->fill($data);
            $consentChanged = $consent !== null && $consent !== $customer->data_consent;
            if ($consentChanged) {
                $customer->recordConsent($consent, $this->auth->guard('sanctum')->user());
            }
            $customer->save();

            if ($creating && $openingBalance !== 0) {
                $this->ledger->post($customer, CustomerTransactionType::Opening, $openingBalance, note: 'رصيد قبل استخدام السيستم');
            }

            $properties = array_filter([
                'opening_balance' => $openingBalance ?: null,
                'credit_limit' => ! $creating && $limitBefore !== $customer->credit_limit ? [$limitBefore, $customer->credit_limit] : null,
            ]);
            if ($creating) {
                $properties['data_consent'] = $customer->data_consent;
            }
            if ($creating || $properties !== []) {
                $this->audit->record(
                    $creating ? 'customers.created' : 'customers.credit_limit_changed',
                    $creating ? "أضاف العميل «{$customer->name}»" : "غيّر حد الآجل للعميل «{$customer->name}»",
                    $customer,
                    $properties,
                );
            }

            if (! $creating && $consentChanged) {
                $this->audit->record(
                    'customers.consent_recorded',
                    $consent ? "سجّل موافقة العميل «{$customer->name}» على حفظ بياناته" : "سجّل إن العميل «{$customer->name}» مش موافق على حفظ بياناته",
                    $customer,
                    ['data_consent' => $consent],
                );
            }

            return $customer->refresh();
        });
    }
}
