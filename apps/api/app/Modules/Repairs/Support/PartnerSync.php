<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\ShopOrders\Contracts\PartnerRepairs;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * A ticket taken in from a partner's repair order moves the order along: work started → preparing,
 * every device of the order ready (or not fixable) → ready, all handed back → delivered, with each
 * device's bill as its price. Called inside the ticket's transaction.
 */
final class PartnerSync
{
    public function __construct(
        private readonly PartnerRepairs $partners,
        private readonly Auth $auth,
    ) {}

    public function ticketMoved(RepairTicket $ticket): void
    {
        $userId = $this->auth->guard('sanctum')->id();
        if ($ticket->partner_order_id === null || $userId === null) {
            return;
        }
        $orderId = $ticket->partner_order_id;

        if ($ticket->status === TicketStatus::Delivered && $ticket->partner_item_id !== null) {
            $this->partners->price($orderId, $ticket->partner_item_id, $ticket->total);
        }

        $statuses = RepairTicket::query()->where('partner_order_id', $orderId)->pluck('status')
            ->map(fn ($s) => $s instanceof TicketStatus ? $s : TicketStatus::from((string) $s));
        $all = fn (array $in) => $statuses->every(fn (TicketStatus $s) => in_array($s, $in, true));

        $to = match (true) {
            $all([TicketStatus::Delivered]) => 'delivered',
            $all([TicketStatus::Ready, TicketStatus::Rejected, TicketStatus::Delivered]) => 'ready',
            $ticket->status !== TicketStatus::Received => 'preparing',
            default => null,
        };
        if ($to !== null) {
            $this->partners->progress($orderId, (string) $userId, $to);
        }
    }
}
