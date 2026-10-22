<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Contracts\TechnicianEarnings;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\CommissionRule;
use App\Modules\Repairs\Models\RepairTicket;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class Earnings implements TechnicianEarnings
{
    public function between(array $userIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $out = array_fill_keys($userIds, ['tickets' => 0, 'commission' => 0, 'clawback' => 0]);
        foreach ($this->earned($userIds, $from, $to) as $t) {
            $out[$t->technician_id]['tickets']++;
            $out[$t->technician_id]['commission'] += $t->commission;
        }
        foreach ($this->clawbacks($userIds, $from, $to) as [$userId, $amount]) {
            $out[$userId]['clawback'] += $amount;
        }

        return $out;
    }

    public function lines(string $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        $lines = $this->earned([$userId], $from, $to)->map(fn (RepairTicket $t) => [
            'ticket_id' => $t->id,
            'reference' => $t->reference(),
            'device' => $t->device_name,
            'delivered_at' => $t->delivered_at?->toIso8601String(),
            'labor' => $t->labor - $t->discount,
            'parts' => $t->parts_total,
            'rule' => $t->commission_rule,
            'amount' => $t->commission,
            'clawback' => false,
        ])->all();
        foreach ($this->clawbacks([$userId], $from, $to) as [, $amount, $back, $first]) {
            $lines[] = [
                'ticket_id' => $back->id,
                'reference' => $back->reference(),
                'device' => "{$back->device_name} — رجوع في الضمان من {$first->reference()}",
                'delivered_at' => $back->received_at?->toIso8601String(),
                'labor' => 0,
                'parts' => $back->parts_total,
                'rule' => 'خصم عمولة الرجوع في الضمان',
                'amount' => -$amount,
                'clawback' => true,
            ];
        }

        return array_values($lines);
    }

    /**
     * @param  list<string>  $userIds
     * @return Collection<int, RepairTicket>
     */
    private function earned(array $userIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return RepairTicket::query()
            ->whereIn('technician_id', $userIds)
            ->where('status', TicketStatus::Delivered->value)
            ->whereBetween('delivered_at', [$from, $to])
            ->where('commission', '>', 0)
            ->orderByDesc('delivered_at')
            ->get();
    }

    /**
     * Warranty returns received in the period whose first ticket's technician gives it back.
     *
     * @param  list<string>  $userIds
     * @return list<array{0: string, 1: int, 2: RepairTicket, 3: RepairTicket}>
     */
    private function clawbacks(array $userIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $giveBack = CommissionRule::query()->whereIn('user_id', $userIds)->where('warranty_clawback', true)->pluck('user_id')->all();
        if ($giveBack === []) {
            return [];
        }
        $backs = RepairTicket::query()->whereNotNull('warranty_of_id')->whereBetween('received_at', [$from, $to])->get();
        $firsts = RepairTicket::query()->whereIn('id', $backs->pluck('warranty_of_id')->all())->whereIn('technician_id', $giveBack)->where('commission', '>', 0)->get()->keyBy('id');
        $out = [];
        foreach ($backs as $back) {
            $first = $firsts[$back->warranty_of_id] ?? null;
            if ($first !== null) {
                $out[] = [$first->technician_id, $first->commission, $back, $first];
            }
        }

        return $out;
    }
}
