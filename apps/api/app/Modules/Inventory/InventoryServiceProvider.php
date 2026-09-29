<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Modules\ModuleServiceProvider;

final class InventoryServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockLedger::class, StockLedgerService::class);
        $this->app->bind(SerialRegistry::class, SerialRegistryService::class);
    }
}
