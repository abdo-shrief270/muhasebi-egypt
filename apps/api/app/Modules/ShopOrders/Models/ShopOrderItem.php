<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Models;

use App\Support\Tenancy\SharedBetweenTenants;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $shop_order_id
 * @property string $description
 * @property int $quantity
 * @property int|null $unit_price
 * @property string|null $device_model
 * @property string|null $imei
 * @property string|null $note
 */
#[Fillable(['shop_order_id', 'buyer_tenant_id', 'seller_tenant_id', 'description', 'quantity', 'unit_price', 'device_model', 'imei', 'note'])]
final class ShopOrderItem extends Model
{
    use SharedBetweenTenants;

    public static function tenantColumns(): array
    {
        return ['buyer_tenant_id', 'seller_tenant_id'];
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }
}
