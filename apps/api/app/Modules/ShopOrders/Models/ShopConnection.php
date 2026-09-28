<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Models;

use App\Modules\ShopOrders\Enums\ConnectionStatus;
use App\Support\Tenancy\SharedBetweenTenants;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $requester_tenant_id
 * @property string $addressee_tenant_id
 * @property ConnectionStatus $status
 * @property string $requested_by
 * @property CarbonImmutable|null $responded_at
 */
#[Fillable(['requester_tenant_id', 'addressee_tenant_id', 'status', 'requested_by', 'responded_at'])]
final class ShopConnection extends Model
{
    use HasUuids, SharedBetweenTenants;

    public static function tenantColumns(): array
    {
        return ['requester_tenant_id', 'addressee_tenant_id'];
    }

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'responded_at' => 'immutable_datetime',
        ];
    }

    public function otherParty(string $tenantId): string
    {
        return $this->requester_tenant_id === $tenantId ? $this->addressee_tenant_id : $this->requester_tenant_id;
    }

    /** The connection (any status) between two shops, whichever of them asked first. */
    public static function between(string $a, string $b): ?self
    {
        return self::withoutTenancy()
            ->where(fn ($q) => $q->where('requester_tenant_id', $a)->where('addressee_tenant_id', $b))
            ->orWhere(fn ($q) => $q->where('requester_tenant_id', $b)->where('addressee_tenant_id', $a))
            ->first();
    }
}
