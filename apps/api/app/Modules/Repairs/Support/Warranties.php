<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Models\RepairWarranty;
use App\Support\Exceptions\DomainRuleException;

/**
 * A delivered repair's warranty, item by item, and the ticket's `warranty_days` / `warranty_until`
 * kept as the longest still standing (what «رجوع في الضمان» checks).
 */
final class Warranties
{
    /**
     * Starts the warranty at delivery: `$items` = [{part_id (null = the labor), days}], or, when
     * the client sends none, `$wholeDays` for the whole repair as before.
     *
     * @param  list<array{part_id: int|null, days: int}>|null  $items
     */
    public static function start(RepairTicket $ticket, ?array $items, int $wholeDays): void
    {
        $now = now();
        $rows = [];
        if ($items === null) {
            if ($wholeDays > 0) {
                $rows[] = ['part_id' => null, 'kind' => 'repair', 'name' => 'الصيانة كلها', 'days' => $wholeDays];
            }
        } else {
            $parts = $ticket->parts()->get()->keyBy('id');
            foreach ($items as $item) {
                if ($item['days'] <= 0) {
                    continue;
                }
                if ($item['part_id'] === null) {
                    $rows[] = ['part_id' => null, 'kind' => 'labor', 'name' => 'المصنعية', 'days' => $item['days']];

                    continue;
                }
                /** @var RepairTicketPart|null $part */
                $part = $parts[$item['part_id']] ?? throw new DomainRuleException('القطعة دي مش في التذكرة.', 'part_not_found', 404);
                $rows[] = ['part_id' => $part->id, 'kind' => 'part', 'name' => $part->name, 'days' => $item['days']];
            }
        }
        foreach ($rows as $row) {
            RepairWarranty::create([
                ...$row,
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'branch_id' => $ticket->branch_id,
                'customer_id' => $ticket->customer_id,
                'device_id' => $ticket->device_id,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($row['days']),
            ]);
        }
        self::sync($ticket);
    }

    /** The ticket's summary columns from its standing items (call after adding or voiding one). */
    public static function sync(RepairTicket $ticket): void
    {
        $standing = RepairWarranty::query()->where('ticket_id', $ticket->id)->whereNull('voided_at')->get();
        $ticket->warranty_days = (int) $standing->max('days');
        $ticket->warranty_until = $standing->max('ends_at');
    }
}
