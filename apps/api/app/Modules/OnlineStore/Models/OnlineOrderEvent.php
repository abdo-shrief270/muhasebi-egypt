<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A line of an order's timeline. Append-only.
 *
 * @property int $id
 * @property OrderStatus $status
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'order_id', 'status', 'note', 'user_name', 'created_at'])]
final class OnlineOrderEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Order events are append-only.'));
        self::deleting(fn () => throw new LogicException('Order events are append-only.'));
    }

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'created_at' => 'datetime'];
    }
}
