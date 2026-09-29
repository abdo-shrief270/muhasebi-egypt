<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $notes
 * @property int $balance piasters; > 0 = we owe them
 * @property bool $is_active
 */
#[Fillable(['tenant_id', 'name', 'phone', 'notes', 'is_active'])]
final class Supplier extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = [
        'balance' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SupplierTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
