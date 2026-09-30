<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Events\DefectivePartRemoved;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * A part taken back off the device: it returns to stock at the cost it left with, its serials in stock
 * again — unless it turned out defective: then it stays out of stock (serials kept aside as damaged)
 * and DefectivePartRemoved lets the returns bin take it.
 */
final class RemovePartAction
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
        private readonly EventRecorder $events,
    ) {}

    public function handle(RepairTicket $ticket, RepairTicketPart $part, bool $defective = false, ?string $reason = null): RepairTicket
    {
        if (! $ticket->status->isOpen()) {
            throw new DomainRuleException('الجهاز اتسلّم خلاص؛ مينفعش تشيل قطع.', 'ticket_closed');
        }

        return DB::transaction(function () use ($ticket, $part, $defective, $reason): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $reference = new StockReference(
                MovementType::RepairReturn,
                refType: 'repair_ticket',
                refId: $ticket->id,
                note: $ticket->reference(),
            );
            if (! $defective) {
                $this->stock->receive($ticket->branch_id, $part->variant_id, $part->qty, $part->unit_cost, $reference);
            }
            if ($part->serials !== null && $part->serials !== []) {
                $this->serials->takeBack($ticket->branch_id, $part->variant_id, $part->serials, $reference, restock: ! $defective);
            }
            $part->delete();
            $ticket->recalculate();
            $ticket->save();

            if ($defective) {
                $this->events->record(new DefectivePartRemoved(
                    tenantId: $ticket->tenant_id,
                    ticketId: $ticket->id,
                    ticketReference: $ticket->reference(),
                    branchId: $ticket->branch_id,
                    variantId: $part->variant_id,
                    qty: $part->qty,
                    unitCost: $part->unit_cost,
                    serials: $part->serials ?: null,
                    reason: $reason,
                ));
            }
            $this->timeline->add($ticket, EventType::PartRemoved, "{$part->qty} × {$part->name}".($defective ? ' (طلعت بايظة)' : ''));
            $this->audit->record(
                'repairs.part_removed',
                "شال {$part->qty} × {$part->name} من التذكرة {$ticket->reference()} ".($defective ? 'وطلعت بايظة (مرجعتش المخزن)' : 'ورجعت المخزن'),
                $ticket,
                $defective ? ['defective' => true] : [],
            );

            return $ticket;
        });
    }
}
