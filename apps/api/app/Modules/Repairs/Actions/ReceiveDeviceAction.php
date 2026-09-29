<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Faults;
use App\Modules\Repairs\Support\TicketMoney;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes a device in: who left it, what it is, how it looked and worked, what the customer says is
 * wrong, when it was promised — and any deposit, into the receiver's drawer.
 */
final class ReceiveDeviceAction
{
    public function __construct(
        private readonly CustomerAccounts $customers,
        private readonly DocumentNumbers $numbers,
        private readonly Timeline $timeline,
        private readonly TicketMoney $money,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated intake (see ReceiveDeviceRequest)
     * @param  list<array{method: string, amount: int}>  $deposits
     */
    public function handle(string $tenantId, string $branchId, array $data, array $deposits): RepairTicket
    {
        return DB::transaction(function () use ($tenantId, $branchId, $data, $deposits): RepairTicket {
            $customer = isset($data['customer_id'])
                ? ($this->customers->find((string) $data['customer_id']) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404))
                : $this->customers->findOrCreate((string) ($data['customer_name'] ?? ''), (string) ($data['customer_phone'] ?? ''));
            if ($customer->phone === null) {
                throw new DomainRuleException('العميل ده ملوش موبايل؛ ضيفه عشان نقدر نبلّغه.', 'customer_phone_missing');
            }

            $user = $this->auth->guard('sanctum')->user();
            $ticket = RepairTicket::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'number' => $this->numbers->next($tenantId, 'repair_ticket'),
                'status' => TicketStatus::Received,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'device_model_id' => $data['device_model_id'] ?? null,
                'device_name' => $data['device_name'],
                'imei' => $data['imei'] ?? null,
                'color' => $data['color'] ?? null,
                'unlock_type' => $data['unlock_type'] ?? 'none',
                'unlock_code' => ($data['unlock_type'] ?? 'none') === 'none' ? null : ($data['unlock_code'] ?? null),
                'accessories' => array_values($data['accessories'] ?? []),
                'condition' => array_values($data['condition'] ?? []),
                'checks' => $data['checks'] ?? [],
                'reported_faults' => Faults::snapshot(array_map('intval', $data['fault_ids'] ?? [])),
                'reported_note' => $data['reported_note'] ?? null,
                'received_by' => $user?->getAuthIdentifier(),
                'received_by_name' => $user?->getAttribute('name'),
                'received_at' => now(),
                'expected_at' => $data['expected_at'] ?? null,
                'technician_id' => $data['technician_id'] ?? null,
                'technician_name' => $data['technician_name'] ?? null,
                'estimate' => $data['estimate'] ?? null,
                'warranty_of_id' => $data['warranty_of_id'] ?? null,
                'public_token' => Str::random(24),
            ]);
            $this->timeline->add($ticket, EventType::Received, $data['reported_note'] ?? null, to: TicketStatus::Received);

            foreach ($deposits as $deposit) {
                $this->money->take($ticket, 'deposit', $deposit['method'], $deposit['amount']);
            }
            if ($ticket->paid !== 0) {
                $ticket->save();
                $this->timeline->add($ticket, EventType::Payment, 'عربون '.number_format($ticket->paid / 100, 2).' ج');
            }

            return $ticket;
        });
    }
}
