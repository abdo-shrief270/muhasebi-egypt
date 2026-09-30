<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Modules\Services\Enums\MoneySource;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Enums\WithdrawFeeMode;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An operation on a service account. Append-only: a mistake is corrected by a reversal (a new row
 * with reverses_id and every amount negated).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $account_id
 * @property int $number
 * @property OperationType $type
 * @property int $amount
 * @property int $fee
 * @property int|null $suggested_fee
 * @property int $balance_change
 * @property int $balance_after
 * @property int $cost_change
 * @property int $cost_after
 * @property int $cash
 * @property int $profit
 * @property WithdrawFeeMode|null $fee_mode
 * @property MoneySource|null $source
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $reference
 * @property string|null $note
 * @property string|null $reverses_id
 * @property string|null $user_id
 * @property string|null $user_name
 * @property Carbon $created_at
 * @property-read ServiceAccount $account
 * @property-read ServiceTransaction|null $reversal
 * @property-read ServiceTransaction|null $reversed
 */
#[Fillable([
    'tenant_id', 'branch_id', 'account_id', 'number', 'type', 'amount', 'fee', 'suggested_fee',
    'balance_change', 'balance_after', 'cost_change', 'cost_after', 'cash', 'profit', 'fee_mode', 'source',
    'customer_name', 'customer_phone', 'reference', 'note', 'reverses_id', 'user_id', 'user_name', 'created_at',
])]
final class ServiceTransaction extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Service transactions are append-only.'));
        self::deleting(fn () => throw new LogicException('Service transactions are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'fee_mode' => WithdrawFeeMode::class,
            'source' => MoneySource::class,
            'number' => 'integer',
            'amount' => 'integer',
            'fee' => 'integer',
            'suggested_fee' => 'integer',
            'balance_change' => 'integer',
            'balance_after' => 'integer',
            'cost_change' => 'integer',
            'cost_after' => 'integer',
            'cash' => 'integer',
            'profit' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ServiceAccount::class, 'account_id');
    }

    /**
     * The row that cancelled this one.
     *
     * @return HasOne<ServiceTransaction, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(ServiceTransaction::class, 'reverses_id');
    }

    /**
     * The row this one cancels.
     *
     * @return BelongsTo<ServiceTransaction, $this>
     */
    public function reversed(): BelongsTo
    {
        return $this->belongsTo(ServiceTransaction::class, 'reverses_id');
    }

    public function isReversal(): bool
    {
        return $this->reverses_id !== null;
    }
}
