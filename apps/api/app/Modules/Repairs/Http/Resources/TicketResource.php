<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Resources;

use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairTicketEvent;
use App\Modules\Repairs\Models\RepairTicketPart;
use App\Modules\Repairs\Models\RepairTicketPayment;
use App\Modules\Repairs\Support\Faults;
use App\Modules\Repairs\Support\IntakeOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RepairTicket
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $technical = (bool) $user?->can('repairs.update_status');
        $label = fn (array $keys, array $map) => array_map(fn ($k) => $map[$k] ?? $k, $keys);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'reference' => $this->reference(),
            'branch_id' => $this->branch_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'next_statuses' => array_map(fn (TicketStatus $s) => ['value' => $s->value, 'label' => $s->label()], $this->status->next()),
            // Not while the device is still at a partner shop.
            'can_deliver' => $this->status->canDeliver() && ! ($this->resource->isOutsourced() && $this->outsourced_status !== 'delivered'),
            'is_overdue' => $this->isOverdue(),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'device_model_id' => $this->device_model_id,
            'device_name' => $this->device_name,
            'imei' => $this->imei,
            'color' => $this->color,
            'unlock_type' => $this->unlock_type,
            // The code opens the customer's phone: only for whoever works on it.
            'unlock_code' => $technical ? $this->unlock_code : null,
            'accessories' => $this->accessories,
            'accessories_labels' => $label($this->accessories, IntakeOptions::ACCESSORIES),
            'condition' => $this->condition,
            'condition_labels' => $label($this->condition, IntakeOptions::CONDITION),
            'checks' => $this->checks,
            'reported_faults' => $this->reported_faults,
            'reported_note' => $this->reported_note,
            'diagnosed_faults' => $this->diagnosed_faults,
            'diagnosis_note' => $this->diagnosis_note,
            'suggested_labor' => $this->whenLoaded('parts', fn () => Faults::suggestedLabor($this->diagnosed_faults ?? $this->reported_faults)),
            'received_by_name' => $this->received_by_name,
            'received_at' => $this->received_at->toIso8601String(),
            'expected_at' => $this->expected_at?->toIso8601String(),
            'technician_id' => $this->technician_id,
            'technician_name' => $this->technician_name,
            'ready_at' => $this->ready_at?->toIso8601String(),
            // Set by the controller for ready tickets: when the customer was told on WhatsApp.
            'ready_notified_at' => $this->resource->getAttributes()['ready_notified_at'] ?? null,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delivered_by_name' => $this->delivered_by_name,
            'estimate' => $this->estimate,
            'labor' => $this->labor,
            'parts_total' => $this->parts_total,
            'parts_cost' => $user?->can('products.view_cost') ? $this->parts_cost : null,
            // Sent to a partner shop for repair: where it is, and the partner's price for whoever sees costs.
            'outsourced' => $this->outsourced_order_id === null ? null : [
                'order_id' => $this->outsourced_order_id,
                'reference' => $this->outsourced_reference,
                'shop' => $this->outsourced_shop,
                'status' => $this->outsourced_status,
                'active' => $this->resource->isOutsourced(),
                'cost' => $user?->can('products.view_cost') || $user?->can('reports.profit') ? $this->outsource_cost : null,
            ],
            // Taken in from a partner shop's repair order.
            'partner' => $this->partner_order_id === null ? null : ['order_id' => $this->partner_order_id, 'reference' => $this->partner_reference],
            'discount' => $this->discount,
            'total' => $this->total,
            'paid' => $this->paid,
            'credit' => $this->credit,
            'due' => $this->due(),
            // What the technician earns: for them, and for whoever sees profits.
            'commission' => $user?->can('reports.profit') || ($this->technician_id !== null && $this->technician_id === $user?->getAuthIdentifier()) ? $this->commission : null,
            'commission_rule' => $this->commission_rule,
            'warranty_days' => $this->warranty_days,
            'warranty_until' => $this->warranty_until?->toIso8601String(),
            'under_warranty' => $this->underWarranty(),
            'warranty_of_id' => $this->warranty_of_id,
            'public_token' => $this->public_token,
            'parts' => $this->whenLoaded('parts', fn () => $this->parts->map(fn (RepairTicketPart $p) => [
                'id' => $p->id,
                'variant_id' => $p->variant_id,
                'name' => $p->name,
                'qty' => $p->qty,
                'unit_price' => $p->unit_price,
                'line_total' => $p->qty * $p->unit_price,
                'serials' => $p->serials,
                'added_by_name' => $p->added_by_name,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (RepairTicketPayment $p) => [
                'kind' => $p->kind,
                'method' => $p->method,
                'amount' => $p->amount,
                'user_name' => $p->user_name,
                'created_at' => $p->created_at->toIso8601String(),
            ])->all()),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn (RepairTicketEvent $e) => [
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'from_status_label' => $e->from_status?->label(),
                'to_status' => $e->to_status?->value,
                'to_status_label' => $e->to_status?->label(),
                'note' => $e->note,
                'user_name' => $e->user_name,
                'created_at' => $e->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
