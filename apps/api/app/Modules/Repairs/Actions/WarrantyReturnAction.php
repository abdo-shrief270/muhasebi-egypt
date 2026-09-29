<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Repairs\Models\FaultType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Exceptions\DomainRuleException;

/** The device came back within its warranty: a new ticket for the same customer and device, linked to the first. */
final class WarrantyReturnAction
{
    public function __construct(private readonly ReceiveDeviceAction $receive) {}

    public function handle(RepairTicket $original, string $branchId, ?string $note): RepairTicket
    {
        if (! $original->underWarranty()) {
            throw new DomainRuleException('الجهاز ده خرج من الضمان أو ملوش ضمان.', 'warranty_expired');
        }

        return $this->receive->handle($original->tenant_id, $branchId, [
            'customer_id' => $original->customer_id,
            'device_model_id' => $original->device_model_id,
            'device_name' => $original->device_name,
            'imei' => $original->imei,
            'color' => $original->color,
            'unlock_type' => $original->unlock_type,
            'unlock_code' => $original->unlock_code,
            'accessories' => [],
            'condition' => [],
            'checks' => [],
            // Only faults still on the shop's lists (one may have been removed since).
            'fault_ids' => FaultType::query()->whereIn('id', array_column($original->diagnosed_faults ?? $original->reported_faults, 'id'))->pluck('id')->all(),
            'reported_note' => trim("رجوع في الضمان من {$original->reference()}. ".($note ?? '')),
            'technician_id' => $original->technician_id,
            'technician_name' => $original->technician_name,
            'warranty_of_id' => $original->id,
        ], []);
    }
}
