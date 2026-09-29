<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property list<string>|null $serials
 * @property int $purchase_item_id
 * @property string $variant_id
 * @property int $qty
 * @property int $unit_cost
 * @property int $line_total
 */
#[Fillable(['tenant_id', 'purchase_return_id', 'purchase_item_id', 'variant_id', 'qty', 'unit_cost', 'line_total', 'serials'])]
final class PurchaseReturnItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'serials' => 'array',
            'qty' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }
}
