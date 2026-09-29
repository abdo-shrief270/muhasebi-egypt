<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Timeline;
use App\Modules\ShopOrders\Contracts\PartnerRepairs;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Sends a device this shop took in to a partner shop for repair (e.g. an accessories shop to a
 * repair shop, or a board job to a specialist). The partner's progress shows on the ticket and
 * its price becomes a cost of the ticket.
 */
final class OutsourceTicketAction
{
    public function __construct(
        private readonly PartnerRepairs $partners,
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
    ) {}

    public function handle(RepairTicket $ticket, string $partnerTenantId, string $userId, ?string $note, ?string $neededBy): RepairTicket
    {
        return DB::transaction(function () use ($ticket, $partnerTenantId, $userId, $note, $neededBy): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (in_array($ticket->status, [TicketStatus::Delivered, TicketStatus::Rejected], true)) {
                throw new DomainRuleException('التذكرة دي اتقفلت.', 'ticket_closed');
            }
            if ($ticket->isOutsourced()) {
                throw new DomainRuleException("الجهاز فعلاً عند «{$ticket->outsourced_shop}».", 'already_outsourced');
            }

            $faults = array_column($ticket->diagnosed_faults ?? $ticket->reported_faults ?? [], 'name');
            $imei = preg_replace('/\D/', '', (string) $ticket->imei);
            $order = $this->partners->send($partnerTenantId, $userId, [
                'description' => mb_substr(implode('، ', array_filter([...$faults, $ticket->reported_note])) ?: 'صيانة', 0, 190),
                'device_model' => mb_substr($ticket->device_name, 0, 120),
                'imei' => strlen((string) $imei) >= 14 && strlen((string) $imei) <= 16 ? $imei : null,
                'note' => $note !== null ? mb_substr($note, 0, 190) : null,
            ], $neededBy);

            $ticket->fill([
                'outsourced_order_id' => $order['id'],
                'outsourced_reference' => $order['reference'],
                'outsourced_shop' => $order['shop_name'],
                'outsourced_status' => 'placed',
                'outsource_cost' => 0,
            ])->save();

            $this->timeline->add($ticket, EventType::Outsourced, "لـ «{$order['shop_name']}» — طلب {$order['reference']}".($note ? " — {$note}" : ''));
            $this->audit->record('repairs.outsourced', "بعت الجهاز في التذكرة {$ticket->reference()} لمحل «{$order['shop_name']}» ({$order['reference']})", $ticket);

            return $ticket;
        });
    }
}
