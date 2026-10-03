<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Sales\Enums\PriceLevel;
use App\Modules\Sales\Enums\SaleStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A completed sale. Amounts are piasters.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property int $number
 * @property SaleStatus $status
 * @property PriceLevel $price_level
 * @property string|null $customer_id
 * @property int $credit piasters put on the customer's account
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $paid
 * @property int $change
 * @property int $cost_total
 * @property int $refunded
 * @property int $refunded_cost
 * @property string|null $notes
 * @property string|null $cashier_name
 * @property string $public_token
 * @property string|null $origin_type where it came from when not the counter (online_order)
 * @property string|null $origin_id
 * @property Carbon $completed_at
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, SalePayment> $payments
 * @property-read Collection<int, SaleReturn> $returns
 */
#[Fillable([
    'id', 'tenant_id', 'branch_id', 'number', 'status', 'price_level', 'customer_id', 'customer_name', 'customer_phone', 'credit',
    'subtotal', 'discount', 'total', 'paid', 'change', 'cost_total', 'refunded', 'refunded_cost', 'notes',
    'cashier_id', 'cashier_name', 'public_token', 'completed_at', 'origin_type', 'origin_id',
])]
final class Sale extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'price_level' => PriceLevel::class,
            'number' => 'integer',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'credit' => 'integer',
            'change' => 'integer',
            'cost_total' => 'integer',
            'refunded' => 'integer',
            'refunded_cost' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'INV-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('id');
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class)->orderBy('created_at')->orderBy('id');
    }
}
