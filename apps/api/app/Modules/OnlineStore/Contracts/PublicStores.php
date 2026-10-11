<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Contracts;

/** A shop's own online store, for other places that link to it (the marketplace's shop page). */
interface PublicStores
{
    /** The open store's address; null when the shop has none, it's closed or the module isn't usable. */
    public function url(string $tenantId): ?string;
}
