<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Events\TicketDelivered;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Commission;
use App\Modules\Repairs\Support\PartnerSync;
use App\Modules\Repairs\Support\TicketMoney;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hands the device back and settles the bill: what's due is paid (change only from cash, or on
 * the customer's account with customers.credit), or a deposit bigger than the bill is refunded in
 * cash. A repaired device starts its warranty.
 */
final class DeliverTicketAction
{
    public function __construct(
        private readonly TicketMoney $money,
        private readonly CustomerAccounts $customers,
        private readonly Timeline $timeline,
        private readonly PartnerSync $partnerSync,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
        private readonly Auth $auth,
        private readonly FeatureAccess $features,
    ) {}

    /**
     * @param  list<array{method: string, amount: int}>  $payments
     */
    public function handle(RepairTicket $ticket, array $payments, int $warrantyDays, ?string $note, bool $canCredit): RepairTicket
    {
        return DB::transaction(function () use ($ticket, $payments, $warrantyDays, $note, $canCredit): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $ticket->status->canDeliver()) {
                throw new DomainRuleException('الجهاز لسه مش جاهز للتسليم.', 'ticket_not_ready', context: ['status' => $ticket->status->value]);
            }
            if ($ticket->isOutsourced() && $ticket->outsourced_status !== 'delivered') {
                throw new DomainRuleException("الجهاز لسه عند «{$ticket->outsourced_shop}».", 'ticket_outsourced');
            }

            $due = $ticket->due();
            $credit = array_sum(array_map(fn ($p) => $p['method'] === 'credit' ? $p['amount'] : 0, $payments));
            $cash = array_sum(array_map(fn ($p) => $p['method'] === 'cash' ? $p['amount'] : 0, $payments));
            $offered = array_sum(array_column($payments, 'amount'));

            if ($due <= 0 && $payments !== []) {
                throw new DomainRuleException('الحساب متدفع؛ مفيش حاجة تتحصّل.', 'nothing_due');
            }
            if ($credit > 0) {
                $this->features->ensure('customers.credit_sales');
            }
            if ($credit > 0 && ! $canCredit) {
                throw new DomainRuleException('مش معاك صلاحية الآجل.', 'credit_not_allowed', 403);
            }
            if ($due > 0 && $offered < $due) {
                throw new DomainRuleException('المدفوع أقل من المطلوب.', 'underpaid', context: ['missing' => $due - $offered]);
            }
            $change = $due > 0 ? max(0, $offered - $due) : 0;
            if ($change > $cash) {
                throw new DomainRuleException('الفيزا والمحافظ والآجل مينفعش يزيدوا عن المطلوب؛ الباقي بيرجع من الكاش بس.', 'overpaid_non_cash');
            }

            foreach ($payments as $payment) {
                if ($payment['method'] === 'credit') {
                    $this->customers->chargeRepair($ticket->customer_id, $payment['amount'], $ticket->id, $ticket->reference(), $ticket->branch_id);
                    $ticket->credit += $payment['amount'];

                    continue;
                }
                $amount = $payment['method'] === 'cash' ? $payment['amount'] - $change : $payment['amount'];
                $this->money->take($ticket, 'payment', $payment['method'], $amount);
            }
            if ($due < 0) {
                // The deposit was more than the bill (rejected, or cheaper than quoted): hand it back.
                $this->money->take($ticket, 'refund', 'cash', $due);
            }

            $repaired = $ticket->status === TicketStatus::Ready;
            $from = $ticket->status;
            $user = $this->auth->guard('sanctum')->user();
            $ticket->status = TicketStatus::Delivered;
            $ticket->delivered_at = now();
            $ticket->delivered_by = $user?->getAuthIdentifier();
            $ticket->delivered_by_name = $user?->getAttribute('name');
            $ticket->warranty_days = $repaired ? $warrantyDays : 0;
            $ticket->warranty_until = $repaired && $warrantyDays > 0 ? now()->addDays($warrantyDays) : null;
            // The technician's cut, fixed now by their rule (only for a device that was repaired).
            // None at all when the owner switched commissions off.
            [$ticket->commission, $ticket->commission_rule] = $repaired && $this->features->enabled('repairs.commission') ? Commission::for($ticket) : [0, null];
            $ticket->save();

            $this->timeline->add($ticket, EventType::Delivered, $note, $from, TicketStatus::Delivered);
            $this->partnerSync->ticketMoved($ticket);
            $this->audit->record(
                'repairs.delivered',
                "سلّم الجهاز في التذكرة {$ticket->reference()} ({$ticket->device_name}) بحساب ".number_format($ticket->total / 100, 2).' ج',
                $ticket,
                ['total' => $ticket->total, 'paid' => $ticket->paid, 'credit' => $ticket->credit],
            );
            $this->events->record(new TicketDelivered($ticket->tenant_id, $ticket->id, $ticket->branch_id, $repaired, $ticket->total, $ticket->parts_cost, $ticket->technician_id));

            return $ticket;
        });
    }
}
