<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Enums\CustomerTransactionType;
use App\Modules\Customers\Enums\PaymentMethod;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A line of a customer's account statement. Append-only.
 *
 * @property string $id
 * @property string $customer_id
 * @property CustomerTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property PaymentMethod|null $payment_method
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $reference
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'customer_id', 'branch_id', 'type', 'amount', 'balance_after', 'payment_method', 'ref_type', 'ref_id', 'reference', 'note', 'user_id', 'user_name', 'created_at'])]
final class CustomerTransaction extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Customer transactions are append-only.'));
        self::deleting(fn () => throw new LogicException('Customer transactions are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => CustomerTransactionType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
