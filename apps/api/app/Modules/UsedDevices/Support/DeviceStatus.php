<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

use App\Modules\Catalog\Contracts\UsedDeviceCatalog;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Sales\Contracts\UnitSales;
use App\Modules\UsedDevices\Models\UsedDevice;
use Illuminate\Support\Collection;

/**
 * Brings devices in line with their stock and sale: in stock (sellable at the POS), sold (with the
 * invoice and what it sold for, off the POS), or gone some other way.
 */
final class DeviceStatus
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly UnitSales $sales,
        private readonly UsedDeviceCatalog $catalog,
    ) {}

    /**
     * @param  Collection<int, UsedDevice>  $devices
     */
    public function refresh(Collection $devices): void
    {
        if ($devices->isEmpty()) {
            return;
        }
        $sales = $this->sales->lastSales($devices->pluck('variant_id')->all());

        foreach ($devices as $device) {
            $inStock = $this->stock->quantity($device->branch_id, $device->variant_id) > 0;
            $sale = $inStock ? null : ($sales[$device->variant_id] ?? null);

            $device->fill([
                'status' => $inStock ? UsedDevice::IN_STOCK : ($sale !== null ? UsedDevice::SOLD : UsedDevice::GONE),
                'sale_id' => $sale?->saleId,
                'sale_reference' => $sale?->reference,
                'sale_price' => $sale?->price,
                'sold_at' => $sale?->soldAt ?? ($inStock ? null : ($device->sold_at ?? now())),
            ]);
            if ($device->isDirty('status')) {
                $this->catalog->setUnitActive($device->variant_id, $inStock);
            }
            $device->save();
        }
    }
}
