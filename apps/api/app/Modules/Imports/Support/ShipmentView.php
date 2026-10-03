<?php

declare(strict_types=1);

namespace App\Modules\Imports\Support;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Imports\Models\ImportAttachment;
use App\Modules\Imports\Models\ImportContact;
use App\Modules\Imports\Models\ImportPayment;
use App\Modules\Imports\Models\ImportShipment;
use App\Modules\Imports\Models\ImportShipmentCost;
use App\Modules\Imports\Models\ImportShipmentItem;
use App\Support\Time\ShopDay;

/** A shipment as the screens show it (the list row, or everything on its page). */
final class ShipmentView
{
    public function __construct(private readonly BranchDirectory $branches) {}

    /** @return array<string, mixed> */
    public function row(ImportShipment $s): array
    {
        $late = $s->status->isOpen() && $s->expected_on !== null && $s->expected_on->lt(ShopDay::today());

        return [
            'id' => $s->id,
            'number' => $s->number,
            'reference' => $s->reference(),
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'contact' => $s->relationLoaded('contact') && $s->contact ? ['id' => $s->contact->id, 'name' => $s->contact->name] : ['id' => $s->contact_id, 'name' => null],
            'branch' => ['id' => $s->branch_id, 'name' => $this->branches->all()[$s->branch_id] ?? null],
            'ordered_on' => $s->ordered_on->toDateString(),
            'expected_on' => $s->expected_on?->toDateString(),
            'late' => $late,
            'allocation' => $s->allocation,
            'original_amount' => $s->original_amount,
            'goods_total' => $s->goods_total,
            'costs_total' => $s->costs_total,
            'total' => $s->goods_total + $s->costs_total,
            'notes' => $s->notes,
            'received_at' => $s->received_at?->toIso8601String(),
            'received_by_name' => $s->received_by_name,
            'cancel_reason' => $s->cancel_reason,
            'created_at' => $s->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function full(ImportShipment $s): array
    {
        $s->loadMissing(['contact', 'items', 'costs', 'payments', 'attachments']);
        $contacts = ImportContact::query()->whereIn('id', $s->costs->pluck('contact_id')->filter()->unique())->pluck('name', 'id');

        return [
            ...$this->row($s),
            'items' => $s->items->map(fn (ImportShipmentItem $i) => [
                'id' => $i->id, 'variant_id' => $i->variant_id, 'name' => $i->name, 'track_serial' => $i->track_serial,
                'qty' => $i->qty, 'unit_price' => $i->unit_price, 'line_total' => $i->qty * $i->unit_price,
                'received_qty' => $i->received_qty, 'damaged_qty' => $i->damaged_qty, 'landed_unit_cost' => $i->landed_unit_cost,
                'serials' => $i->serials ?? [],
            ])->values()->all(),
            'costs' => $s->costs->map(fn (ImportShipmentCost $c) => [
                'id' => $c->id, 'kind' => $c->kind, 'kind_label' => ImportShipmentCost::KINDS[$c->kind] ?? $c->kind,
                'contact' => $c->contact_id ? ['id' => $c->contact_id, 'name' => $contacts[$c->contact_id] ?? null] : null,
                'amount' => $c->amount, 'note' => $c->note,
            ])->values()->all(),
            'payments' => $s->payments->map(fn (ImportPayment $p) => $p->toApi())->values()->all(),
            'paid' => (int) $s->payments->whereNull('reversed_at')->sum('amount'),
            'attachments' => $s->attachments->map(fn (ImportAttachment $a) => [
                'id' => $a->id, 'kind' => $a->kind, 'kind_label' => ImportAttachment::KINDS[$a->kind] ?? $a->kind,
                'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size, 'uploaded_by_name' => $a->uploaded_by_name,
                'created_at' => $a->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
