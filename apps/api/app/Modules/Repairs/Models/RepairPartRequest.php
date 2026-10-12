<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A technician asking the store for a part for a ticket; handing it out puts it on the ticket.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $ticket_id
 * @property string $variant_id
 * @property string $name
 * @property int $qty
 * @property string|null $note
 * @property string $status
 * @property string|null $requested_by
 * @property string|null $requested_by_name
 * @property string|null $handled_by_name
 * @property Carbon|null $handled_at
 * @property string|null $reason
 * @property int|null $part_id
 * @property Carbon $created_at
 * @property-read RepairTicket $ticket
 */
#[Fillable(['tenant_id', 'branch_id', 'ticket_id', 'variant_id', 'name', 'qty', 'note', 'status', 'requested_by', 'requested_by_name', 'handled_by', 'handled_by_name', 'handled_at', 'reason', 'part_id'])]
final class RepairPartRequest extends Model
{
    use BelongsToTenant, HasUuids;

    public const REQUESTED = 'requested';

    public const HANDED = 'handed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const LABELS = [
        self::REQUESTED => 'مستني الصرف',
        self::HANDED => 'اتصرفت',
        self::REJECTED => 'اترفضت',
        self::CANCELLED => 'اتلغت',
    ];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'part_id' => 'integer', 'handled_at' => 'datetime'];
    }

    /** @return BelongsTo<RepairTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }
}
