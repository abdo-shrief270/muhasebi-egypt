<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Contracts;

/** A shop's own online store, for other places that link to it or order through it («سوق محاسبي»). */
interface PublicStores
{
    /**
     * The open store: its slug (for its public endpoints), address and whether it takes orders
     * in the app (else the cart goes to the shop on WhatsApp). Null when the shop has none, it's
     * closed or the module isn't usable.
     *
     * @return array{slug: string, url: string, takes_orders: bool, whatsapp: ?string}|null
     */
    public function find(string $tenantId): ?array;

    /**
     * Shops whose open store takes repair bookings («احجز صيانة»; the repairs module usable).
     *
     * @return array<string, array{slug: string, note: ?string}> tenant id => store
     */
    public function repairShops(): array;
}
