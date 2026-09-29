<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Sales\Enums\PaymentMethod;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $sale_id
 * @property int $number
 * @property int $total
 * @property int $cost
 * @property PaymentMethod $refund_method
 * @property string|null $reason
 * @property string|null $created_by_name
 * @property-read Collection<int, SaleReturnItem> $items
 */
#[Fillable(['tenant_id', 'branch_id', 'sale_id', 'number', 'total', 'cost', 'refund_method', 'reason', 'created_by', 'created_by_name'])]
final class SaleReturn extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'total' => 'integer',
            'cost' => 'integer',
            'refund_method' => PaymentMethod::class,
        ];
    }

    public function reference(): string
    {
        return 'RET-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return HasMany<SaleReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
