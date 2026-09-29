<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop's customer-data retention: customers with no activity for retention_years are erased.
 *
 * @property string $tenant_id
 * @property int|null $retention_years null = keep until erased by hand
 * @property string|null $updated_by_name
 */
#[Table('customer_privacy_settings', key: 'tenant_id', keyType: 'string', incrementing: false)]
#[Fillable(['tenant_id', 'retention_years', 'updated_by_name'])]
final class CustomerPrivacySetting extends Model
{
    use BelongsToTenant;

    public const MIN_YEARS = 1;

    public const MAX_YEARS = 10;

    protected function casts(): array
    {
        return ['retention_years' => 'integer'];
    }
}
