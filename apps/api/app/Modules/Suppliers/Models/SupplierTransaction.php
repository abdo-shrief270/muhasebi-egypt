<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A line of a supplier's account statement. Append-only.
 *
 * @property string $id
 * @property string $supplier_id
 * @property SupplierTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property PaymentMethod|null $payment_method
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'supplier_id', 'type', 'amount', 'balance_after', 'payment_method', 'ref_type', 'ref_id', 'note', 'user_id', 'user_name', 'created_at'])]
final class SupplierTransaction extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Supplier transactions are append-only.'));
        self::deleting(fn () => throw new LogicException('Supplier transactions are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => SupplierTransactionType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
