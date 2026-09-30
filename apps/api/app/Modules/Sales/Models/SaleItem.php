<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property list<string>|null $serials
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
 * @property-read Sale $sale
 */
#[Fillable(['tenant_id', 'sale_id', 'variant_id', 'name', 'barcode', 'qty', 'unit_price', 'discount', 'line_total', 'unit_cost', 'returned_qty', 'serials'])]
final class SaleItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'serials' => 'array',
            'qty' => 'integer',
            'unit_price' => 'integer',
            'discount' => 'integer',
            'line_total' => 'integer',
            'unit_cost' => 'integer',
            'returned_qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<SaleReturnItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
