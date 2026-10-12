<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Actions\RepairBookingActions;
use App\Modules\OnlineStore\Contracts\PublicStores;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Support\Modules\ModuleAccess;

final class PublicStoreLinks implements PublicStores
{
    public function __construct(private readonly ModuleAccess $modules) {}

    public function find(string $tenantId): ?array
    {
        if (! $this->modules->enabled('online_store', $tenantId)) {
            return null;
        }
        $store = OnlineStore::withoutTenancy()->where('tenant_id', $tenantId)->first();
        if ($store === null || ! $store->isOpen()) {
            return null;
        }

        return ['slug' => $store->slug, 'url' => $store->url(), 'takes_orders' => $store->takesOrders(), 'whatsapp' => $store->whatsapp];
    }

    public function repairShops(): array
    {
        $out = [];
        foreach (OnlineStore::withoutTenancy()->where('repair_booking', true)->get() as $store) {
            /** @var OnlineStore $store */
            if ($store->isOpen() && $this->modules->enabled('online_store', $store->tenant_id) && RepairBookingActions::offered($store, $this->modules)) {
                $out[$store->tenant_id] = ['slug' => $store->slug, 'note' => $store->repair_booking_note];
            }
        }

        return $out;
    }
}
