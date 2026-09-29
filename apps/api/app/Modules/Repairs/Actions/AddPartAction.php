<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;

/**
 * A part fitted: it leaves the ticket branch's stock now (FIFO, like a sale) and is billed at
 * its retail price unless the technician sets another.
 */
final class AddPartAction
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly Timeline $timeline,
        private readonly Auth $auth,
    ) {}

    public function handle(RepairTicket $ticket, string $variantId, int $qty, ?int $unitPrice): RepairTicketPart
    {
        if (! $ticket->status->isOpen()) {
            throw new DomainRuleException('الجهاز اتسلّم خلاص؛ مينفعش تضيف قطع.', 'ticket_closed');
        }
        $variant = $this->catalog->find([$variantId])[$variantId] ?? throw new DomainRuleException('الصنف مش موجود.', 'variant_not_found', 404);

        return DB::transaction(function () use ($ticket, $variant, $qty, $unitPrice): RepairTicketPart {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $issue = $this->stock->issue($ticket->branch_id, $variant->id, $qty, new StockReference(
                MovementType::RepairUse,
                refType: 'repair_ticket',
                refId: $ticket->id,
                note: $ticket->reference(),
            ));

            $user = $this->auth->guard('sanctum')->user();
            $part = RepairTicketPart::create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'variant_id' => $variant->id,
                'name' => $variant->displayName(),
                'qty' => $qty,
                'unit_price' => $unitPrice ?? $variant->priceRetail,
                'unit_cost' => $issue->unitCost(),
                'added_by' => $user?->getAuthIdentifier(),
                'added_by_name' => $user?->getAttribute('name'),
            ]);
            $ticket->recalculate();
            $ticket->save();
            $this->timeline->add($ticket, EventType::PartAdded, "{$qty} × {$part->name}");

            return $part;
        });
    }
}
