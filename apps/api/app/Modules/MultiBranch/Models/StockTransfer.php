<?php

declare(strict_types=1);

namespace App\Modules\MultiBranch\Models;

use App\Modules\MultiBranch\Enums\TransferStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Goods moving from one branch to another (TR-00001). Stock leaves the sending branch when it's
 * shipped (at its FIFO cost) and enters the receiving one at the same cost when it's received.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $number
 * @property string $from_branch_id
 * @property string $to_branch_id
 * @property TransferStatus $status
 * @property string|null $notes
 * @property string|null $requested_by_name
 * @property string|null $shipped_by_name
 * @property Carbon|null $shipped_at
 * @property string|null $received_by_name
 * @property Carbon|null $received_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'number', 'from_branch_id', 'to_branch_id', 'status', 'notes', 'requested_by_name',
    'shipped_by_name', 'shipped_at', 'received_by_name', 'received_at', 'cancel_reason',
])]
final class StockTransfer extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'number' => 'integer',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /** @return HasMany<StockTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }

    public function reference(): string
    {
        return 'TR-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }
}
