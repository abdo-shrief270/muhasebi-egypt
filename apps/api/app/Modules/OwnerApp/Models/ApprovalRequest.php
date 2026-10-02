<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Models;

use App\Modules\OwnerApp\Contracts\ApprovalKind;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $branch_id
 * @property ApprovalKind $kind
 * @property string $summary
 * @property int $amount
 * @property string $payload_hash
 * @property string $requested_by
 * @property string $requested_by_name
 * @property string $status pending | approved | denied | expired
 * @property string|null $via app | pin
 * @property string|null $decided_by
 * @property string|null $decided_by_name
 * @property Carbon|null $decided_at
 * @property string|null $reason
 * @property Carbon|null $used_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'branch_id', 'kind', 'summary', 'amount', 'payload_hash', 'requested_by', 'requested_by_name',
    'status', 'via', 'decided_by', 'decided_by_name', 'decided_at', 'reason', 'used_at', 'expires_at',
])]
final class ApprovalRequest extends Model
{
    use BelongsToTenant, HasUuids;

    /** How long a request waits, and how long an approval stays usable. */
    public const MINUTES = 10;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'kind' => ApprovalKind::class,
            'amount' => 'integer',
            'decided_at' => 'datetime',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Pending past its time reads as expired. */
    public function currentStatus(): string
    {
        return $this->status === 'pending' && $this->expires_at->isPast() ? 'expired' : $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'summary' => $this->summary,
            'amount' => $this->amount,
            'branch_id' => $this->branch_id,
            'requested_by_name' => $this->requested_by_name,
            'status' => $this->currentStatus(),
            'via' => $this->via,
            'decided_by_name' => $this->decided_by_name,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'reason' => $this->reason,
            'used' => $this->used_at !== null,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
