<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * How long a seller's national ID and card photos are kept after their data was erased, counted
 * from their last sale to the shop (anti-theft record).
 *
 * @property string $tenant_id
 * @property int $id_retention_years
 * @property string|null $updated_by_name
 */
#[Table('used_device_settings', key: 'tenant_id', keyType: 'string', incrementing: false)]
#[Fillable(['tenant_id', 'id_retention_years', 'updated_by_name'])]
final class UsedDeviceSetting extends Model
{
    use BelongsToTenant;

    public const DEFAULT_YEARS = 3;

    public const MIN_YEARS = 1;

    public const MAX_YEARS = 10;

    protected function casts(): array
    {
        return ['id_retention_years' => 'integer'];
    }

    public static function retentionYears(): int
    {
        return (int) (self::query()->value('id_retention_years') ?? self::DEFAULT_YEARS);
    }
}
