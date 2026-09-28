<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Enums;

enum Party: string
{
    /** The shop that placed the order. */
    case Buyer = 'buyer';

    /** The shop that prepares it. */
    case Seller = 'seller';
}
