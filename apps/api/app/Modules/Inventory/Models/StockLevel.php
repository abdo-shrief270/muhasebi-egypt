<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $variant_id
 * @property int $qty
 * @property int $avg_cost
 */
#[Fillable(['tenant_id', 'branch_id', 'variant_id', 'qty', 'avg_cost'])]
final class StockLevel extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'avg_cost' => 'integer',
        ];
    }
}
