<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

final class ChangeStatusAction
{
    public function __construct(
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
    ) {}

    public function handle(RepairTicket $ticket, TicketStatus $to, ?string $note): RepairTicket
    {
        return DB::transaction(function () use ($ticket, $to, $note): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $ticket->status;
            if (! in_array($to, $from->next(), true)) {
                throw new DomainRuleException("مينفعش التذكرة تتنقل من «{$from->label()}» لـ «{$to->label()}».", 'status_not_allowed', context: ['allowed' => array_map(fn (TicketStatus $s) => $s->value, $from->next())]);
            }

            $ticket->status = $to;
            $ticket->ready_at = $to === TicketStatus::Ready ? now() : ($to === TicketStatus::Repairing ? null : $ticket->ready_at);
            $ticket->save();

            $this->timeline->add($ticket, EventType::Status, $note, $from, $to);
            if ($to === TicketStatus::Rejected) {
                $this->audit->record('repairs.rejected', "التذكرة {$ticket->reference()} اتقفلت من غير إصلاح".($note ? " — {$note}" : ''), $ticket);
            }

            return $ticket;
        });
    }
}
