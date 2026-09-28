<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Enums;

enum OrderType: string
{
    /** Goods: parts, accessories… */
    case Goods = 'goods';

    /** A repair job sent to another shop (e.g. board repair). */
    case Repair = 'repair';

    public function label(): string
    {
        return match ($this) {
            self::Goods => 'بضاعة',
            self::Repair => 'شغل صيانة',
        };
    }
}
