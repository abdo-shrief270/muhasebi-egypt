<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $fault_category_id
 * @property string $name
 * @property int|null $default_labor_price
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['tenant_id', 'fault_category_id', 'name', 'default_labor_price', 'is_active', 'sort'])]
final class FaultType extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'default_labor_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FaultCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FaultCategory::class, 'fault_category_id');
    }
}
