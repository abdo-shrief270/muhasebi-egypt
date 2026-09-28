<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Resources;

use App\Modules\Identity\Contracts\ShopSummary;
use App\Modules\ShopOrders\Models\ShopConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{connection: ShopConnection, tenantId: string, shop: ShopSummary|null} $resource
 */
final class ConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['connection' => $connection, 'tenantId' => $tenantId, 'shop' => $shop] = $this->resource;

        return [
            'id' => $connection->id,
            'status' => $connection->status->value,
            'status_label' => $connection->status->label(),
            'direction' => $connection->requester_tenant_id === $tenantId ? 'outgoing' : 'incoming',
            'shop' => ShopView::of($shop),
            'created_at' => $connection->created_at?->toIso8601String(),
        ];
    }
}
