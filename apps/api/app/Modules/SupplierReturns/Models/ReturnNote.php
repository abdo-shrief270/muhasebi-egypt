<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Models;

use App\Modules\SupplierReturns\Enums\NoteStatus;
use App\Modules\SupplierReturns\Enums\SourceType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A return note (إذن مرتجع): bin units going back to one source.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property int $number
 * @property SourceType $source_type
 * @property string $source_id
 * @property string $source_name
 * @property string|null $source_phone
 * @property NoteStatus $status
 * @property int $units
 * @property int $total_cost
 * @property int $accepted_value
 * @property int $rejected_value
 * @property string|null $resolution
 * @property string|null $refund_method
 * @property string|null $rejected_action
 * @property string|null $notes
 * @property string|null $settle_note
 * @property string|null $created_by_name
 * @property string|null $settled_by_name
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'branch_id', 'number', 'source_type', 'source_id', 'source_name', 'source_phone', 'status', 'units', 'total_cost',
    'accepted_value', 'rejected_value', 'resolution', 'refund_method', 'rejected_action', 'notes', 'settle_note',
    'created_by', 'created_by_name', 'settled_by_name', 'sent_at', 'settled_at'])]
final class ReturnNote extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'supplier_returns';

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'source_type' => SourceType::class,
            'status' => NoteStatus::class,
            'units' => 'integer',
            'total_cost' => 'integer',
            'accepted_value' => 'integer',
            'rejected_value' => 'integer',
            'sent_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<BinItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BinItem::class, 'supplier_return_id')->orderBy('seq');
    }

    public function reference(): string
    {
        return 'SRN-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }
}
