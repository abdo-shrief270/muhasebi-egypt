<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Part of the device the fault is in (screen, board, charging port…). Editable per shop.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property int $sort
 */
#[Fillable(['tenant_id', 'name', 'sort'])]
final class FaultCategory extends Model
{
    use BelongsToTenant;

    /**
     * @return HasMany<FaultType, $this>
     */
    public function types(): HasMany
    {
        return $this->hasMany(FaultType::class)->orderBy('sort');
    }
}
