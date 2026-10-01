<?php

declare(strict_types=1);

namespace App\Modules\Installments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An amount applied to a plan. Append-only.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property string|null $branch_id
 * @property int $amount
 * @property string|null $method
 * @property string $source counter | account
 * @property string|null $customer_transaction_id
 * @property string|null $user_id
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'plan_id', 'branch_id', 'amount', 'method', 'source', 'customer_transaction_id', 'user_id', 'user_name', 'created_at'])]
final class InstallmentPayment extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Installment payments are append-only.'));
        self::deleting(fn () => throw new LogicException('Installment payments are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime'];
    }
}
