<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\ShopOrders\Contracts\PartnerPurchases;
use App\Modules\ShopOrders\Enums\ConnectionStatus;
use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\OrderType;
use App\Modules\ShopOrders\Models\ShopConnection;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Modules\ShopOrders\Models\ShopOrderItem;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

final class PartnerPurchasesService implements PartnerPurchases
{
    private const RECEIVED = [OrderStatus::Delivered, OrderStatus::Completed];

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly ShopDirectory $shops,
    ) {}

    public function recentSellers(int $limit = 5): array
    {
        $orders = ShopOrder::query()
            ->where('buyer_tenant_id', $this->tenant->idOrFail())
            ->where('type', OrderType::Goods)
            ->whereIn('status', self::RECEIVED)
            ->orderByDesc('delivered_at')
            ->limit(100)
            ->get()
            ->unique('seller_tenant_id')
            ->take($limit)
            ->values();
        $shops = $this->shops->findMany($orders->pluck('seller_tenant_id')->all());

        return $orders->map(fn (ShopOrder $o): array => [
            'tenant_id' => $o->seller_tenant_id,
            'name' => $shops[$o->seller_tenant_id]->name ?? '',
            'phone' => $shops[$o->seller_tenant_id]->phone ?? null,
            'order_id' => $o->id,
            'reference' => $o->reference(),
            'date' => ($o->getAttribute('delivered_at') ?? $o->created_at)->toDateString(),
        ])->all();
    }

    public function bySerials(array $serials): array
    {
        if ($serials === []) {
            return [];
        }

        $items = ShopOrderItem::query()
            ->where('buyer_tenant_id', $this->tenant->idOrFail())
            ->whereNotNull('imei')
            ->whereIn(DB::raw("upper(regexp_replace(imei, '[\\s\\-/]+', '', 'g'))"), $serials)
            ->get();
        $orders = ShopOrder::query()
            ->whereIn('id', $items->pluck('shop_order_id')->unique()->all())
            ->where('type', OrderType::Goods)
            ->whereIn('status', self::RECEIVED)
            ->get()
            ->keyBy('id');
        $shops = $this->shops->findMany($orders->pluck('seller_tenant_id')->unique()->values()->all());

        $found = [];
        foreach ($items as $item) {
            $order = $orders->get($item->shop_order_id);
            if ($order === null) {
                continue;
            }
            $serial = strtoupper((string) preg_replace('/[\s\-\/]+/u', '', (string) $item->imei));
            $found[$serial] = [
                'tenant_id' => $order->seller_tenant_id,
                'name' => $shops[$order->seller_tenant_id]->name ?? '',
                'phone' => $shops[$order->seller_tenant_id]->phone ?? null,
                'order_id' => $order->id,
                'reference' => $order->reference(),
            ];
        }

        return $found;
    }

    public function partners(): array
    {
        $me = $this->tenant->idOrFail();
        $ids = ShopConnection::query()
            ->where('status', ConnectionStatus::Accepted)
            ->get()
            ->map(fn (ShopConnection $c): string => $c->otherParty($me))
            ->unique()
            ->values()
            ->all();

        $out = [];
        foreach ($this->shops->findMany($ids) as $id => $shop) {
            $out[$id] = ['tenant_id' => $id, 'name' => $shop->name, 'phone' => $shop->phone];
        }

        return $out;
    }
}
