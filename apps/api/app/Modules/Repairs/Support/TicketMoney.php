<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPayment;
use Illuminate\Contracts\Auth\Factory as Auth;

/** Money into or out of a ticket, through the drawer of whoever takes it. Inside the caller's transaction. */
final class TicketMoney
{
    public function __construct(
        private readonly CashDrawer $drawer,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  string  $kind  deposit | payment | refund
     * @param  int  $amount  piasters; negative for a refund
     */
    public function take(RepairTicket $ticket, string $kind, string $method, int $amount): void
    {
        if ($amount === 0) {
            return;
        }
        $this->drawer->record($ticket->branch_id, DrawerEntry::Repair, $method, $amount, 'repair_ticket', $ticket->id, $ticket->reference());

        $user = $this->auth->guard('sanctum')->user();
        RepairTicketPayment::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'kind' => $kind,
            'method' => $method,
            'amount' => $amount,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
        $ticket->paid += $amount;
    }
}
