<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Contracts;

/**
 * Goods this shop bought from partner shops, as other modules see them (e.g. to know where a
 * defective unit came from). Read-only; never touches the other shop's side.
 */
interface PartnerPurchases
{
    /**
     * Partner shops this shop received goods orders from lately (one per shop, newest first).
     *
     * @return list<array{tenant_id: string, name: string, phone: string|null, order_id: string, reference: string, date: string}>
     */
    public function recentSellers(int $limit = 5): array;

    /**
     * The goods order this shop received a unit with that IMEI / serial on, if any.
     *
     * @param  list<string>  $serials  normalised (no spaces or dashes, upper case)
     * @return array<string, array{tenant_id: string, name: string, phone: string|null, order_id: string, reference: string}> keyed by serial
     */
    public function bySerials(array $serials): array;

    /**
     * Partner shops (accepted connections).
     *
     * @return array<string, array{tenant_id: string, name: string, phone: string|null}> keyed by tenant id
     */
    public function partners(): array;
}
