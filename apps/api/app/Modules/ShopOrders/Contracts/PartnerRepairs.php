<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Contracts;

/**
 * Repair jobs between partner shops, as the Repairs module sees them: a shop sends a device it
 * took in to a partner ("شغل صيانة" order); the partner works it as its own ticket, and its
 * progress moves the order along. Called inside the caller's transaction.
 */
interface PartnerRepairs
{
    /**
     * Sends one device to a partner shop as a repair order placed by $userId.
     *
     * @param  array{description: string, device_model: string|null, imei: string|null, note: string|null}  $device
     * @return array{id: string, reference: string, shop_name: string}
     */
    public function send(string $partnerTenantId, string $userId, array $device, ?string $neededBy = null): array;

    /**
     * A repair order this shop received (as the partner doing the work), or null for any other order.
     *
     * @return array{id: string, reference: string, from_tenant_id: string, needed_by: string|null, notes: string|null,
     *               items: list<array{id: int, description: string, device_model: string|null, imei: string|null, note: string|null}>}|null
     */
    public function received(string $orderId): ?array;

    /**
     * The partner's work moved on: walk the order forward to $to (preparing | ready | delivered) as far
     * as its rules allow. Never fails: an order the other shop cancelled just stays as it is.
     */
    public function progress(string $orderId, string $userId, string $to): void;

    /** The partner's price for one device (piasters); the order's total follows. */
    public function price(string $orderId, int $itemId, int $amount): void;
}
