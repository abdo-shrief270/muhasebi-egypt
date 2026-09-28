<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Models;

use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\Party;
use App\Support\Tenancy\SharedBetweenTenants;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One step in an order's timeline.
 *
 * @property int $id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property Party $actor_party
 * @property string $actor_user_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Table('shop_order_activities', timestamps: false)]
#[Fillable(['shop_order_id', 'buyer_tenant_id', 'seller_tenant_id', 'from_status', 'to_status', 'actor_party', 'actor_user_id', 'note', 'created_at'])]
final class ShopOrderActivity extends Model
{
    use SharedBetweenTenants;

    public static function tenantColumns(): array
    {
        return ['buyer_tenant_id', 'seller_tenant_id'];
    }

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'actor_party' => Party::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
