<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property string $invoice_prefix
 * @property bool $is_main
 * @property bool $is_active
 */
#[Fillable(['tenant_id', 'name', 'address', 'phone', 'invoice_prefix', 'is_main', 'is_active'])]
final class Branch extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
