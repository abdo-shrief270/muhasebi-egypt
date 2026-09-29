<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An IMEI / serial number and where that unit is now.
 *
 * @property string $id
 * @property string $variant_id
 * @property string $branch_id
 * @property string $serial
 * @property string $status in_stock | out | damaged
 */
#[Fillable(['tenant_id', 'variant_id', 'branch_id', 'serial', 'status'])]
final class SerialNumber extends Model
{
    use BelongsToTenant, HasUuids;

    public const IN_STOCK = 'in_stock';

    public const OUT = 'out';

    public const DAMAGED = 'damaged';

    /**
     * @return HasMany<SerialEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SerialEvent::class, 'serial_id')->orderBy('seq');
    }
}
