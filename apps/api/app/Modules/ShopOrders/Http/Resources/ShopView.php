<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Resources;

use App\Modules\Identity\Contracts\ShopSummary;

final class ShopView
{
    /**
     * @return array{id: string, name: string, code: string, phone: string}|null
     */
    public static function of(?ShopSummary $shop): ?array
    {
        return $shop === null ? null : [
            'id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'phone' => $shop->phone,
        ];
    }
}
