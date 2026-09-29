<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $sale_item_id
 * @property int $qty
 * @property int $unit_refund
 * @property int $line_total
 * @property bool $restocked
 */
#[Fillable(['tenant_id', 'sale_return_id', 'sale_item_id', 'variant_id', 'qty', 'unit_refund', 'line_total', 'restocked'])]
final class SaleReturnItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_refund' => 'integer',
            'line_total' => 'integer',
            'restocked' => 'boolean',
        ];
    }
}
