<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property list<string>|null $serials
 * @property string $purchase_id
 * @property string $variant_id
 * @property string|null $lot_id
 * @property int $qty
 * @property int $unit_cost
 * @property int $net_unit_cost
 * @property int $line_total
 * @property int|null $previous_cost
 * @property int $returned_qty
 * @property-read Purchase $purchase
 */
#[Fillable(['tenant_id', 'purchase_id', 'variant_id', 'lot_id', 'qty', 'unit_cost', 'net_unit_cost', 'line_total', 'previous_cost', 'returned_qty', 'serials'])]
final class PurchaseItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'serials' => 'array',
            'qty' => 'integer',
            'unit_cost' => 'integer',
            'net_unit_cost' => 'integer',
            'line_total' => 'integer',
            'previous_cost' => 'integer',
            'returned_qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** Cost went up compared with the average before this purchase. */
    public function costIncreased(): bool
    {
        return $this->previous_cost !== null && $this->previous_cost > 0 && $this->net_unit_cost > $this->previous_cost;
    }
}
