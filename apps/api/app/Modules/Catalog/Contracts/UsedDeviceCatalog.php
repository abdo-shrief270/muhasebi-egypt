<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/**
 * How a used device bought from a customer becomes sellable: one product per model («Apple iPhone 13
 * مستعمل», in the «موبايلات مستعملة» category, tracking IMEIs, compatible with that model) and one
 * variant per device, with that device's own asking price. The POS finds it by its IMEI like any
 * serial-tracked item. Call the writes inside the caller's transaction.
 */
interface UsedDeviceCatalog
{
    /**
     * The shop's phone models, matching every word of $q (same search as the catalog's model list).
     *
     * @return list<DeviceModelSummary>
     */
    public function deviceModels(?string $q, int $limit = 30): array;

    public function deviceModel(int $id): ?DeviceModelSummary;

    /**
     * Adds one device as its own variant. $deviceModelId links the product to the shop's model
     * list; without it the product is named after $modelName.
     *
     * @param  int  $price  asking price, piasters
     */
    public function addUnit(?int $deviceModelId, string $modelName, string $unitName, int $price): VariantSummary;

    /** A new asking price for the device (written to the price history). */
    public function setUnitPrice(string $variantId, int $price): void;

    /** Sold devices leave the POS and the catalog lists; one brought back returns. */
    public function setUnitActive(string $variantId, bool $active): void;
}
