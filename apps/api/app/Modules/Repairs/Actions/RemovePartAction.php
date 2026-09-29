<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/** A part taken back off the device: it returns to stock at the cost it left with. */
final class RemovePartAction
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
    ) {}

    public function handle(RepairTicket $ticket, RepairTicketPart $part): RepairTicket
    {
        if (! $ticket->status->isOpen()) {
            throw new DomainRuleException('الجهاز اتسلّم خلاص؛ مينفعش تشيل قطع.', 'ticket_closed');
        }

        return DB::transaction(function () use ($ticket, $part): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->stock->receive($ticket->branch_id, $part->variant_id, $part->qty, $part->unit_cost, new StockReference(
                MovementType::RepairReturn,
                refType: 'repair_ticket',
                refId: $ticket->id,
                note: $ticket->reference(),
            ));
            $part->delete();
            $ticket->recalculate();
            $ticket->save();

            $this->timeline->add($ticket, EventType::PartRemoved, "{$part->qty} × {$part->name}");
            $this->audit->record('repairs.part_removed', "شال {$part->qty} × {$part->name} من التذكرة {$ticket->reference()} ورجعت المخزن", $ticket);

            return $ticket;
        });
    }
}
