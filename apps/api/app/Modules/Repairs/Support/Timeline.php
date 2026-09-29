<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketEvent;
use Illuminate\Contracts\Auth\Factory as Auth;

/** Writes a ticket's timeline, stamped with who did it and when. */
final class Timeline
{
    public function __construct(private readonly Auth $auth) {}

    public function add(RepairTicket $ticket, EventType $type, ?string $note = null, ?TicketStatus $from = null, ?TicketStatus $to = null): RepairTicketEvent
    {
        $user = $this->auth->guard('sanctum')->user();

        return RepairTicketEvent::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note !== null ? mb_substr($note, 0, 1000) : null,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
