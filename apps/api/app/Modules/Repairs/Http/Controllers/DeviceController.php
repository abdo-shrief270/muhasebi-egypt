<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Repairs\Models\RepairDevice;
use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Audit\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A customer's devices with each one's repair history (every branch's tickets, newest first):
 * the customer page's «الأجهزة والصيانة» and the next intake of the same device.
 */
final class DeviceController
{
    public function __construct(private readonly BranchDirectory $branches) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'uuid']]);
        $devices = RepairDevice::query()
            ->where('customer_id', $data['customer_id'])
            ->with(['tickets' => fn ($q) => $q->orderByDesc('received_at')])
            ->orderByDesc('last_seen_at')
            ->get();

        return response()->json(['data' => $devices->map(fn (RepairDevice $d) => $this->present($d))->all()]);
    }

    public function show(RepairDevice $device): JsonResponse
    {
        return response()->json(['data' => $this->present($device->load(['tickets' => fn ($q) => $q->orderByDesc('received_at')]))]);
    }

    public function update(Request $request, RepairDevice $device, Auditor $audit): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('repairs.create'), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'imei' => ['sometimes', 'nullable', 'string', 'max:40'],
            'color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [], ['name' => 'اسم الجهاز']);
        $device->fill($data)->save();
        $audit->record('repairs.device', "عدّل بيانات جهاز «{$device->name}»", $device);

        return $this->show($device);
    }

    /** @return array<string, mixed> */
    private function present(RepairDevice $device): array
    {
        $branches = $this->branches->all();
        $tickets = $device->tickets;
        $warranty = $tickets->filter(fn (RepairTicket $t) => $t->underWarranty())->sortByDesc('warranty_until')->first();
        $last = $tickets->first();

        return [
            'id' => $device->id,
            'customer_id' => $device->customer_id,
            'device_model_id' => $device->device_model_id,
            'name' => $device->name,
            'imei' => $device->imei,
            'color' => $device->color,
            'notes' => $device->notes,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'tickets_count' => $tickets->count(),
            'open_ticket_id' => $tickets->first(fn (RepairTicket $t) => $t->status->isOpen())?->id,
            // The newest ticket still under warranty: «رجوع في الضمان» starts from it.
            'warranty_ticket_id' => $warranty?->id,
            'warranty_until' => $warranty?->warranty_until?->toIso8601String(),
            'unlock_type' => $last?->unlock_type,
            'tickets' => $tickets->map(fn (RepairTicket $t) => [
                'id' => $t->id,
                'reference' => $t->reference(),
                'branch_id' => $t->branch_id,
                'branch_name' => $branches[$t->branch_id] ?? null,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'faults' => collect([...($t->diagnosed_faults ?: $t->reported_faults ?: [])])->pluck('name')->filter()->values()->all(),
                'note' => $t->reported_note,
                'technician_name' => $t->technician_name,
                'total' => $t->total,
                'received_at' => $t->received_at?->toIso8601String(),
                'delivered_at' => $t->delivered_at?->toIso8601String(),
                'warranty_until' => $t->warranty_until?->toIso8601String(),
                'under_warranty' => $t->underWarranty(),
                'warranty_return' => $t->warranty_of_id !== null,
            ])->values()->all(),
        ];
    }
}
