<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Contracts\MovementType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of the stock ledger. Append-only: corrections are new movements, never edits.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $variant_id
 * @property string|null $lot_id
 * @property MovementType $type
 * @property int $qty
 * @property int $unit_cost
 * @property int $balance_after
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $reason
 * @property string|null $note
 * @property string|null $user_id
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'branch_id', 'variant_id', 'lot_id', 'type', 'qty', 'unit_cost', 'balance_after',
    'ref_type', 'ref_id', 'reason', 'note', 'user_id', 'user_name', 'created_at',
])]
final class StockMovement extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Stock movements are append-only.'));
        self::deleting(fn () => throw new LogicException('Stock movements are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'qty' => 'integer',
            'unit_cost' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
