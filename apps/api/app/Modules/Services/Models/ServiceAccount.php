<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Modules\Services\Enums\AccountKind;
use App\Modules\Services\Enums\Provider;
use App\Modules\Services\Enums\WithdrawFeeMode;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A wallet or airtime line of a branch. The balance and cost_value move only through ServiceLedger.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property AccountKind $kind
 * @property Provider $provider
 * @property string $name
 * @property string|null $phone
 * @property int $balance piasters
 * @property int $cost_value piasters: what the balance cost the shop
 * @property int|null $daily_limit
 * @property WithdrawFeeMode $withdraw_fee_mode
 * @property bool $is_active
 * @property Carbon|null $created_at
 */
#[Fillable(['tenant_id', 'branch_id', 'kind', 'provider', 'name', 'phone', 'daily_limit', 'withdraw_fee_mode', 'is_active'])]
final class ServiceAccount extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = [
        'balance' => 0,
        'cost_value' => 0,
        'withdraw_fee_mode' => 'cash',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
            'provider' => Provider::class,
            'withdraw_fee_mode' => WithdrawFeeMode::class,
            'balance' => 'integer',
            'cost_value' => 'integer',
            'daily_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ServiceFeeRule, $this>
     */
    public function feeRules(): HasMany
    {
        return $this->hasMany(ServiceFeeRule::class, 'account_id');
    }

    /**
     * @return HasMany<ServiceTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ServiceTransaction::class, 'account_id');
    }

    public function providerLabel(): string
    {
        return $this->provider->label($this->kind);
    }
}
