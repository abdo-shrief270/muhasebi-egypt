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
 * One guaranteed item of a delivered repair: a part fitted, the labor, or (older tickets) the whole
 * repair. Active until `ends_at` unless voided.
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $branch_id
 * @property string $customer_id
 * @property string|null $device_id
 * @property int|null $part_id
 * @property string $kind part | labor | repair
 * @property string $name
 * @property int $days
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property string|null $voided_by_name
 */
#[Fillable(['tenant_id', 'ticket_id', 'branch_id', 'customer_id', 'device_id', 'part_id', 'kind', 'name', 'days', 'starts_at', 'ends_at', 'voided_at', 'void_reason', 'voided_by_name'])]
final class RepairWarranty extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['days' => 'integer', 'part_id' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    /** @return BelongsTo<RepairTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function isActive(): bool
    {
        return $this->voided_at === null && $this->ends_at->isFuture();
    }

    /** active | voided | expired */
    public function state(): string
    {
        return $this->voided_at !== null ? 'voided' : ($this->ends_at->isFuture() ? 'active' : 'expired');
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->name,
            'days' => $this->days,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'state' => $this->state(),
            'void_reason' => $this->void_reason,
            'voided_by_name' => $this->voided_by_name,
            'voided_at' => $this->voided_at?->toIso8601String(),
        ];
    }
}
