<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $purchase_id
 * @property int $number
 * @property int $total
 * @property string|null $notes
 * @property string|null $created_by_name
 * @property-read Collection<int, PurchaseReturnItem> $items
 */
#[Fillable(['tenant_id', 'branch_id', 'supplier_id', 'purchase_id', 'number', 'total', 'notes', 'created_by', 'created_by_name'])]
final class PurchaseReturn extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'total' => 'integer',
        ];
    }

    public function reference(): string
    {
        return 'PRT-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return HasMany<PurchaseReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
