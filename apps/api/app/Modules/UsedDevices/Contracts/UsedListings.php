<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Contracts;

use DateTimeInterface;

/**
 * What «سوق محاسبي» shows of a used device on sale: its grade, battery, storage, colour, the
 * inspection's results and the device photos (never the seller, the card photos or the IMEI).
 */
interface UsedListings
{
    /**
     * The current shop's devices in stock among these variants.
     *
     * @param  list<string>  $variantIds
     * @return array<string, array{device_id: string, grade: string, grade_label: string, battery_health: int|null, storage: string|null, storage_gb: int|null, color: string|null, photos: list<string>, checks: list<array{label: string, ok: bool}>, imei_registered: bool, listed_at: string}> variant id => listing
     */
    public function forVariants(array $variantIds): array;

    /** The current shop's devices in stock with no photo of the device (the market leaves them out). */
    public function withoutPhotos(): int;

    /**
     * Devices whose listing changed (photos added / removed) after $since, in every shop.
     *
     * @return array<string, list<string>> tenant id => variant ids
     */
    public function changedSince(DateTimeInterface $since): array;
}
