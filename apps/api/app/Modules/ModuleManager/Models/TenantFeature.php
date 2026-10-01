<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $feature_key
 * @property bool $enabled
 * @property string|null $value
 * @property string|null $updated_by_name
 */
#[Fillable(['tenant_id', 'feature_key', 'enabled', 'value', 'updated_by_name'])]
final class TenantFeature extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
