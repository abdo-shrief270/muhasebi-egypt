<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property int $sort
 */
#[Fillable(['tenant_id', 'name', 'sort'])]
final class Brand extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return HasMany<DeviceModel, $this>
     */
    public function deviceModels(): HasMany
    {
        return $this->hasMany(DeviceModel::class);
    }
}
