<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Enums\TicketStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A line of a ticket's timeline. Append-only.
 *
 * @property int $id
 * @property EventType $type
 * @property TicketStatus|null $from_status
 * @property TicketStatus|null $to_status
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'ticket_id', 'type', 'from_status', 'to_status', 'note', 'user_id', 'user_name', 'created_at'])]
final class RepairTicketEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Ticket events are append-only.'));
        self::deleting(fn () => throw new LogicException('Ticket events are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
