<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use Illuminate\Support\Facades\DB;

/**
 * A part's price on this ticket changed for this customer (repairs.part_price): the shop's price
 * it replaced is kept (`list_price`, first change only) with the reason. Under the floor
 * (repairs.price_floor: the part's cost or the technician price) only a manager may go
 * (`$overFloor` = repairs.settings). Audited, on the timeline, and the bill follows.
 */
final class SetPartPriceAction
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly FeatureAccess $features,
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
    ) {}

    public function floor(RepairTicketPart $part): int
    {
        if (! $this->features->enabled('repairs.price_floor')) {
            return 0;
        }
        if ($this->features->setting('repairs.price_floor') === 'technician') {
            $variant = $this->catalog->find([$part->variant_id])[$part->variant_id] ?? null;

            return (int) ($variant?->priceTechnician ?? $part->unit_cost);
        }

        return $part->unit_cost;
    }

    public function handle(RepairTicket $ticket, RepairTicketPart $part, int $unitPrice, ?string $note, bool $overFloor): RepairTicket
    {
        if (! $ticket->status->isOpen()) {
            throw new DomainRuleException('الجهاز اتسلّم خلاص؛ مينفعش تغيّر الأسعار.', 'ticket_closed');
        }
        $floor = $this->floor($part);
        if ($unitPrice < $floor && ! $overFloor) {
            throw new DomainRuleException('السعر ده أقل من الحد اللي محدده المحل ('.number_format($floor / 100, 2).' ج). اطلب من المدير.', 'part_price_below_floor');
        }

        return DB::transaction(function () use ($ticket, $part, $unitPrice, $note): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $part = RepairTicketPart::query()->where('ticket_id', $ticket->id)->lockForUpdate()->findOrFail($part->id);
            $was = $part->unit_price;
            if ($was === $unitPrice) {
                return $ticket;
            }
            $part->list_price ??= $was;
            $part->unit_price = $unitPrice;
            $part->price_note = $note;
            $part->save();
            $ticket->recalculate();
            $ticket->save();

            $line = "سعر {$part->name}: ".number_format($was / 100, 2).' ← '.number_format($unitPrice / 100, 2).' ج'.($note ? " ({$note})" : '');
            $this->timeline->add($ticket, EventType::PartPriced, $line);
            $this->audit->record('repairs.part_price', "{$ticket->reference()} — {$line}", $ticket);

            return $ticket;
        });
    }
}
