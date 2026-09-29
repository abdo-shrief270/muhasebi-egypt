<?php

declare(strict_types=1);

namespace App\Modules\Cash\Models;

use App\Modules\Cash\Contracts\ExpenseCategory;
use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Enums\MovementType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Money into or out of a drawer. Append-only.
 *
 * @property string $id
 * @property string $branch_id
 * @property string|null $shift_id
 * @property MovementType $type
 * @property Method $method
 * @property int $amount
 * @property ExpenseCategory|null $category
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'branch_id', 'shift_id', 'type', 'method', 'amount', 'category', 'ref_type', 'ref_id', 'note', 'user_id', 'user_name', 'created_at'])]
final class CashMovement extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Cash movements are append-only.'));
        self::deleting(fn () => throw new LogicException('Cash movements are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'method' => Method::class,
            'category' => ExpenseCategory::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
