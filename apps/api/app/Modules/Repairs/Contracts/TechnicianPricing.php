<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * With «الفني يطلب القطع من المخزن بسعر الفني» on, a technician who only asks for parts (no selling,
 * no product editing, no handing parts out) sees the technician price and nothing else: screens that
 * show a variant's prices ask here first.
 */
interface TechnicianPricing
{
    public function technicianOnly(?Authenticatable $user): bool;

    /** The price a technician pays for a part: the technician price, else retail. */
    public function price(int $retail, ?int $technician): int;
}
