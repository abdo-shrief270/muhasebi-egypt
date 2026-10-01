<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Listeners;

use App\Modules\Repairs\Events\DefectivePartRemoved;
use App\Modules\SupplierReturns\Actions\AddToBinAction;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Modules\FeatureAccess;

/** A repair part that turned out defective goes to the returns bin, with its source. */
final class CollectDefectiveRepairParts extends ModuleListener
{
    public function __construct(private readonly AddToBinAction $bin) {}

    protected function module(): string
    {
        return 'supplier_returns';
    }

    protected function react(DomainEvent $event): void
    {
        // The owner's «التالف يروح السلة لوحده» switch.
        if (! app(FeatureAccess::class)->enabled('supplier_returns.auto_collect', $event->tenantId())) {
            return;
        }
        assert($event instanceof DefectivePartRemoved);

        $this->bin->fromDocument(
            tenantId: $event->tenantId,
            branchId: $event->branchId,
            variantId: $event->variantId,
            qty: $event->qty,
            unitCost: $event->unitCost,
            serials: $event->serials,
            reason: ReturnReason::tryFrom((string) $event->reason),
            origin: 'repair',
            originId: $event->ticketId,
            originLabel: "قطعة بايظة من {$event->ticketReference}",
            issuedRefType: 'repair_ticket',
            issuedRefId: $event->ticketId,
        );
    }
}
