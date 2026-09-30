<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Support;

use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Support\Modules\FeatureAccess;

/** Whether the buyer may see the seller's prices yet (the seller's «prices after review» switch). */
final class ShopOrderPrices
{
    public static function hiddenFromBuyer(ShopOrder $order): bool
    {
        return in_array($order->status, [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Preparing], true)
            && app(FeatureAccess::class)->enabled('shop_orders.prices_after_review', $order->seller_tenant_id);
    }
}
