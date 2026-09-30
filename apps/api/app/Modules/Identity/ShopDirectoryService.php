<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Identity\Contracts\ShopSummary;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Support\ReceiptSettings;

final class ShopDirectoryService implements ShopDirectory
{
    public function findByCode(string $code): ?ShopSummary
    {
        $tenant = Tenant::query()->where('code', strtoupper(trim($code)))->first();

        return $tenant ? $this->summary($tenant) : null;
    }

    public function find(string $tenantId): ?ShopSummary
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant ? $this->summary($tenant) : null;
    }

    public function findMany(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        return Tenant::query()
            ->whereIn('id', array_values(array_unique($tenantIds)))
            ->get()
            ->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => $this->summary($tenant)])
            ->all();
    }

    private function summary(Tenant $tenant): ShopSummary
    {
        return new ShopSummary($tenant->id, $tenant->name, $tenant->code, $tenant->phone, ReceiptSettings::of($tenant));
    }
}
