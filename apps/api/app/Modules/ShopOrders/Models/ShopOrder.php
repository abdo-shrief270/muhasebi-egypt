<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Models;

use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\OrderType;
use App\Modules\ShopOrders\Enums\Party;
use App\Support\Tenancy\SharedBetweenTenants;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * An order one shop (buyer) places with a partner shop (seller). Both shops see it.
 *
 * @property string $id
 * @property int $number
 * @property string $buyer_tenant_id
 * @property string $seller_tenant_id
 * @property OrderType $type
 * @property OrderStatus $status
 * @property CarbonImmutable|null $needed_by
 * @property string|null $notes
 * @property int|null $total
 * @property string $placed_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['number', 'buyer_tenant_id', 'seller_tenant_id', 'type', 'status', 'needed_by', 'notes', 'total', 'placed_by',
    'accepted_at', 'ready_at', 'delivered_at', 'completed_at', 'closed_at'])]
final class ShopOrder extends Model
{
    use HasUuids, SharedBetweenTenants;

    public static function tenantColumns(): array
    {
        return ['buyer_tenant_id', 'seller_tenant_id'];
    }

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'needed_by' => 'immutable_date',
            'total' => 'integer',
            'number' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<ShopOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<ShopOrderActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ShopOrderActivity::class)->orderBy('id');
    }

    public function partyOf(string $tenantId): Party
    {
        return match ($tenantId) {
            $this->buyer_tenant_id => Party::Buyer,
            $this->seller_tenant_id => Party::Seller,
            default => throw new InvalidArgumentException('Tenant is not a party of this order.'),
        };
    }

    public function counterpartyOf(string $tenantId): string
    {
        return $this->partyOf($tenantId) === Party::Buyer ? $this->seller_tenant_id : $this->buyer_tenant_id;
    }

    public function reference(): string
    {
        return 'SO-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}
