<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $product_id
 * @property string $variant_id
 * @property string $name
 * @property int $qty
 * @property int $unit_price
 * @property int $line_total
 */
#[Fillable(['tenant_id', 'order_id', 'product_id', 'variant_id', 'name', 'qty', 'unit_price', 'line_total'])]
final class OnlineOrderItem extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['qty' => 'integer', 'unit_price' => 'integer', 'line_total' => 'integer'];
    }
}
