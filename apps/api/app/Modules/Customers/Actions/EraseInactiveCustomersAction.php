<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Repairs\Contracts\CustomerRepairs;
use App\Modules\Sales\Contracts\CustomerSales;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The shop's retention rule, for the current shop: customers with nothing on their account, no
 * device in the shop and no activity (account, invoice, repair) for $years are erased through
 * EraseCustomerAction, like an erasure by hand.
 */
final class EraseInactiveCustomersAction
{
    public function __construct(
        private readonly EraseCustomerAction $erase,
        private readonly CustomerSales $sales,
        private readonly CustomerRepairs $repairs,
    ) {}

    /** @return int how many were erased */
    public function handle(int $years): int
    {
        $erased = 0;
        $cutoff = now()->subYears($years);

        $this->candidates($cutoff)->chunkById(200, function (Collection $customers) use ($cutoff, &$erased): void {
            foreach ($customers as $customer) {
                /** @var Customer $customer */
                if (! $this->inactiveSince($customer, $cutoff)) {
                    continue;
                }
                try {
                    $this->erase->handle($customer, EraseCustomerAction::BY_RETENTION);
                    $erased++;
                } catch (DomainRuleException) {
                    // A balance or a device appeared meanwhile: next time.
                }
            }
        });

        return $erased;
    }

    /**
     * Not erased, nothing on the account, and the account itself quiet since $cutoff.
     *
     * @return Builder<Customer>
     */
    private function candidates(Carbon $cutoff): Builder
    {
        return Customer::query()
            ->whereNull('erased_at')
            ->where('balance', 0)
            ->whereRaw('coalesce(last_activity_at, created_at) < ?', [$cutoff]);
    }

    private function inactiveSince(Customer $customer, Carbon $cutoff): bool
    {
        $lastSale = $this->sales->lastSaleAt($customer->id);
        $lastRepair = $this->repairs->lastActivityAt($customer->id);

        return ($lastSale === null || $lastSale->lt($cutoff))
            && ($lastRepair === null || $lastRepair->lt($cutoff))
            && $this->repairs->openCount($customer->id) === 0;
    }
}
