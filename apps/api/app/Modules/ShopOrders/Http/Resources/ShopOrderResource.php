<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Resources;

use App\Modules\Identity\Contracts\ShopSummary;
use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Modules\ShopOrders\Models\ShopOrderActivity;
use App\Modules\ShopOrders\Models\ShopOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{order: ShopOrder, tenantId: string, counterparty: ShopSummary|null} $resource
 */
final class ShopOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['order' => $order, 'tenantId' => $tenantId, 'counterparty' => $counterparty] = $this->resource;
        $party = $order->partyOf($tenantId);

        return [
            'id' => $order->id,
            'reference' => $order->reference(),
            'type' => $order->type->value,
            'type_label' => $order->type->label(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'my_party' => $party->value,
            'counterparty' => ShopView::of($counterparty),
            'needed_by' => $order->needed_by?->toDateString(),
            'notes' => $order->notes,
            'total' => $order->total,
            'created_at' => $order->created_at->toIso8601String(),
            'allowed_transitions' => array_map(
                fn (OrderStatus $status): array => ['status' => $status->value, 'label' => $status->label()],
                $order->status->nextFor($party),
            ),
            'items_count' => $order->relationLoaded('items') ? $order->items->count() : null,
            'items' => $this->when($order->relationLoaded('items'), fn () => $order->items->map(fn (ShopOrderItem $item): array => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'device_model' => $item->device_model,
                'imei' => $item->imei,
                'note' => $item->note,
            ])->all()),
            'activities' => $this->when($order->relationLoaded('activities'), fn () => $order->activities->map(fn (ShopOrderActivity $a): array => [
                'to_status' => $a->to_status->value,
                'to_status_label' => $a->to_status->label(),
                'by' => $a->actor_party->value,
                'note' => $a->note,
                'at' => $a->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
