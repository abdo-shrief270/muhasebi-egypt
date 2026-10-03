<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A line of a contact's statement. Append-only.
 *
 * @property int $id
 * @property string $type shipment | cost | payment | claim | reversal
 * @property int $amount + the shop owes more, - less
 * @property int $balance_after
 * @property string|null $ref_type
 * @property string|null $ref_id
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'contact_id', 'type', 'amount', 'balance_after', 'ref_type', 'ref_id', 'note', 'user_name', 'created_at'])]
final class ImportContactTransaction extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const TYPES = ['shipment' => 'شحنة', 'cost' => 'مصاريف', 'payment' => 'دفعة', 'claim' => 'مطالبة نواقص / تالف', 'reversal' => 'تصحيح'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Statement lines are append-only.'));
        self::deleting(fn () => throw new LogicException('Statement lines are append-only.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }
}
