<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $sale_id
 * @property string $variant_id
 * @property string $name
 * @property string|null $barcode
 * @property int $qty
 * @property int $unit_price
 * @property int $discount
 * @property int $line_total
 * @property int $unit_cost
 * @property int $returned_qty
 */
#[Fillable(['tenant_id', 'sale_id', 'variant_id', 'name', 'barcode', 'qty', 'unit_price', 'discount', 'line_total', 'unit_cost', 'returned_qty'])]
final class SaleItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'integer',
            'discount' => 'integer',
            'line_total' => 'integer',
            'unit_cost' => 'integer',
            'returned_qty' => 'integer',
        ];
    }
}
