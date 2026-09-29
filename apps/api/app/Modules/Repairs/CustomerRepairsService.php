<?php

declare(strict_types=1);

namespace App\Modules\Repairs;

use App\Modules\Repairs\Contracts\CustomerRepairs;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketEvent;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Models\RepairTicketPayment;
use App\Modules\Repairs\Support\IntakeOptions;
use Illuminate\Support\Carbon;

final class CustomerRepairsService implements CustomerRepairs
{
    public function openCount(string $customerId): int
    {
        return RepairTicket::query()
            ->where('customer_id', $customerId)
            ->where('status', '!=', TicketStatus::Delivered->value)
            ->count();
    }

    public function lastActivityAt(string $customerId): ?Carbon
    {
        $at = RepairTicket::query()
            ->where('customer_id', $customerId)
            ->selectRaw('max(greatest(received_at, delivered_at)) as last_at')
            ->value('last_at');

        return $at === null ? null : Carbon::parse((string) $at);
    }

    public function forCustomer(string $customerId): array
    {
        $label = fn (array $keys, array $map) => array_map(fn ($k) => $map[$k] ?? $k, $keys);

        return RepairTicket::query()
            ->where('customer_id', $customerId)
            ->with(['parts', 'payments', 'events'])
            ->orderBy('received_at')
            ->get()
            ->map(fn (RepairTicket $t): array => [
                'reference' => $t->reference(),
                'status' => $t->status->label(),
                'customer_name' => $t->customer_name,
                'customer_phone' => $t->customer_phone,
                'device_name' => $t->device_name,
                'imei' => $t->imei,
                'color' => $t->color,
                'accessories' => $label($t->accessories, IntakeOptions::ACCESSORIES),
                'condition' => $label($t->condition, IntakeOptions::CONDITION),
                'reported_faults' => array_column($t->reported_faults, 'name'),
                'reported_note' => $t->reported_note,
                'diagnosed_faults' => array_column($t->diagnosed_faults ?? [], 'name'),
                'diagnosis_note' => $t->diagnosis_note,
                'received_at' => $t->received_at->toIso8601String(),
                'delivered_at' => $t->delivered_at?->toIso8601String(),
                'labor' => $t->labor,
                'parts_total' => $t->parts_total,
                'discount' => $t->discount,
                'total' => $t->total,
                'paid' => $t->paid,
                'credit' => $t->credit,
                'warranty_until' => $t->warranty_until?->toIso8601String(),
                'parts' => $t->parts->map(fn (RepairTicketPart $p): array => [
                    'name' => $p->name,
                    'qty' => $p->qty,
                    'unit_price' => $p->unit_price,
                ])->all(),
                'payments' => $t->payments->map(fn (RepairTicketPayment $p): array => [
                    'kind' => $p->kind,
                    'method' => $p->method,
                    'amount' => $p->amount,
                    'created_at' => $p->created_at->toIso8601String(),
                ])->all(),
                'timeline' => $t->events->map(fn (RepairTicketEvent $e): array => [
                    'type' => $e->type->value,
                    'to_status' => $e->to_status?->label(),
                    'note' => $e->note,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->all(),
            ])
            ->values()
            ->all();
    }
}
