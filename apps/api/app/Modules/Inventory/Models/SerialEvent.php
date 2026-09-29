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
 * One step in a serial's life. Append-only.
 *
 * @property string $id
 * @property string $serial_id
 * @property string $branch_id
 * @property MovementType $type
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'serial_id', 'branch_id', 'type', 'ref_type', 'ref_id', 'note', 'user_name', 'created_at'])]
final class SerialEvent extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Serial events are append-only.'));
        self::deleting(fn () => throw new LogicException('Serial events are append-only.'));
    }

    protected function casts(): array
    {
        return ['type' => MovementType::class, 'created_at' => 'datetime'];
    }
}
