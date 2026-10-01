<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Listeners;

use App\Modules\Sales\Events\SaleRefunded;
use App\Modules\SupplierReturns\Actions\AddToBinAction;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Modules\FeatureAccess;

/** Units a customer brought back damaged (not restocked) go to the returns bin, with their source. */
final class CollectDamagedSaleReturns extends ModuleListener
{
    public function __construct(private readonly AddToBinAction $bin) {}

    protected function module(): string
    {
        return 'supplier_returns';
    }

    protected function shouldReact(DomainEvent $event): bool
    {
        return $event instanceof SaleRefunded && $event->damaged !== [];
    }

    protected function react(DomainEvent $event): void
    {
        // The owner's «التالف يروح السلة لوحده» switch.
        if (! app(FeatureAccess::class)->enabled('supplier_returns.auto_collect', $event->tenantId())) {
            return;
        }
        assert($event instanceof SaleRefunded);

        foreach ($event->damaged as $line) {
            $this->bin->fromDocument(
                tenantId: $event->tenantId,
                branchId: $event->branchId,
                variantId: $line['variant_id'],
                qty: (int) $line['qty'],
                unitCost: (int) $line['unit_cost'],
                serials: $line['serials'] ?? null,
                reason: ReturnReason::tryFrom((string) ($line['reason'] ?? '')),
                origin: 'sale_return',
                originId: $event->returnId,
                originLabel: trim('مرتجع عميل '.($event->returnReference ?? '').($event->saleReference ? " من {$event->saleReference}" : '')),
                issuedRefType: 'sale',
                issuedRefId: $event->saleId,
            );
        }
    }
}
