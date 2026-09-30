<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Modules\FeatureAccess;
use Illuminate\Http\JsonResponse;

/**
 * The tracking page behind the receipt's QR code: status and dates, the device and the bill —
 * no unlock code, and only the end of the IMEI.
 */
final class PublicTicketController
{
    public function show(string $token, ShopDirectory $shops, FeatureAccess $features): JsonResponse
    {
        $ticket = RepairTicket::query()->where('public_token', $token)->firstOrFail();
        // The shop may have turned the tracking page off.
        abort_unless($features->enabled('repairs.public_tracking', $ticket->tenant_id), 404);
        $shop = $shops->find($ticket->tenant_id);

        return response()->json(['data' => [
            'shop' => $shop ? ['name' => $shop->name, 'phone' => $shop->phone] : null,
            'reference' => $ticket->reference(),
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'steps' => array_map(fn (TicketStatus $s) => ['value' => $s->value, 'label' => $s->label()], [TicketStatus::Received, TicketStatus::Repairing, TicketStatus::Ready, TicketStatus::Delivered]),
            'customer_name' => $ticket->customer_name,
            'device_name' => $ticket->device_name,
            'imei_tail' => $ticket->imei ? substr($ticket->imei, -4) : null,
            'faults' => array_column($ticket->diagnosed_faults ?? $ticket->reported_faults, 'name'),
            'received_at' => $ticket->received_at->toIso8601String(),
            'expected_at' => $ticket->expected_at?->toIso8601String(),
            'ready_at' => $ticket->ready_at?->toIso8601String(),
            'delivered_at' => $ticket->delivered_at?->toIso8601String(),
            'total' => $ticket->total,
            'paid' => $ticket->paid,
            'due' => max(0, $ticket->due()),
            'warranty_until' => $ticket->warranty_until?->toIso8601String(),
        ]]);
    }
}
