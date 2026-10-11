<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Contracts\PublicStores;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Support\Modules\ModuleAccess;

final class PublicStoreLinks implements PublicStores
{
    public function __construct(private readonly ModuleAccess $modules) {}

    public function url(string $tenantId): ?string
    {
        if (! $this->modules->enabled('online_store', $tenantId)) {
            return null;
        }
        $store = OnlineStore::withoutTenancy()->where('tenant_id', $tenantId)->first();

        return $store !== null && $store->isOpen() ? $store->url() : null;
    }
}
