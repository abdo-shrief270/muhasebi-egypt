<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\CustomerLedger;
use App\Modules\Repairs\Contracts\CustomerRepairs;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Privacy\Anonymised;
use App\Support\Privacy\PhoneFingerprint;
use Illuminate\Support\Facades\DB;

/**
 * Right to erasure (Personal Data Protection Law 151/2020): the customer's name becomes
 * «عميل محذوف» and their phone and notes go, while every amount — account statement, invoices,
 * tickets, drawer — stays. Other modules anonymise their copies on CustomerErased. Refused while
 * money is still open between the shop and the customer or a device of theirs is in the shop.
 * Erasing an erased customer does nothing.
 */
final class EraseCustomerAction
{
    public const BY_OWNER = 'owner';

    public const BY_RETENTION = 'retention';

    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly CustomerRepairs $repairs,
        private readonly CashDrawer $drawer,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  self::BY_*  $reason
     *
     * @throws DomainRuleException customer_has_balance, customer_has_open_repairs
     */
    public function handle(Customer $customer, string $reason = self::BY_OWNER): Customer
    {
        return DB::transaction(function () use ($customer, $reason): Customer {
            $locked = $this->ledger->lock($customer->id) ?? $customer;
            if ($locked->isErased()) {
                return $locked;
            }
            if ($locked->balance !== 0) {
                throw new DomainRuleException(
                    $locked->balance > 0 ? 'العميل لسه عليه فلوس؛ حصّلها الأول وبعدين امسح بياناته.' : 'العميل ليه رصيد عندك؛ رجّعهوله الأول وبعدين امسح بياناته.',
                    'customer_has_balance',
                    context: ['balance' => $locked->balance],
                );
            }
            $open = $this->repairs->openCount($locked->id);
            if ($open > 0) {
                throw new DomainRuleException('العميل ليه أجهزة لسه في الصيانة؛ سلّمها الأول وبعدين امسح بياناته.', 'customer_has_open_repairs', context: ['open_tickets' => $open]);
            }

            $name = $locked->name;
            $fingerprint = PhoneFingerprint::of($locked->phone);

            $locked->forceFill([
                'name' => Anonymised::NAME,
                'phone' => null,
                'notes' => null,
                'is_active' => false,
                'erased_at' => now(),
            ])->save();

            // Copies of the name kept by this module and the drawer, inside the same transaction.
            $payments = $locked->transactions()->where('type', CustomerTransactionType::Payment->value)->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->drawer->redactNotes('customer_transaction', $payments, 'تحصيل من '.Anonymised::NAME);
            $this->audit->redact($locked, $name, Anonymised::NAME);

            $this->events->record(new CustomerErased($locked->tenant_id, $locked->id, $fingerprint));
            $this->audit->record(
                'customers.erased',
                $reason === self::BY_RETENTION
                    ? 'مسح بيانات عميل تلقائياً (مالوش حركة من مدة الاحتفاظ)'
                    : 'مسح بيانات عميل (بقى «'.Anonymised::NAME.'»)',
                $locked,
                ['reason' => $reason],
            );

            return $locked->refresh();
        });
    }
}
