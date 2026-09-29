<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $variant_id
 * @property string $source_type
 * @property string|null $source_id
 * @property int $unit_cost
 * @property int $qty_in
 * @property int $qty_remaining
 */
#[Fillable(['tenant_id', 'branch_id', 'variant_id', 'source_type', 'source_id', 'unit_cost', 'qty_in', 'qty_remaining', 'received_at'])]
final class StockLot extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'qty_in' => 'integer',
            'qty_remaining' => 'integer',
            'received_at' => 'datetime',
        ];
    }
}
