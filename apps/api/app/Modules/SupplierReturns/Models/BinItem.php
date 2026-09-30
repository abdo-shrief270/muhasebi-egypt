<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Models;

use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Modules\SupplierReturns\Enums\SourceType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units in the returns bin (out of sellable stock) and, once a note takes them, on that note.
 * One unit per row for products that track serials.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string|null $supplier_return_id
 * @property string $variant_id
 * @property string $variant_name
 * @property int $qty
 * @property string|null $serial
 * @property int $unit_cost
 * @property string|null $lot_id
 * @property SourceType|null $source_type
 * @property string|null $source_id
 * @property string|null $source_name
 * @property string|null $source_doc
 * @property string|null $detected_by serial | lot | manual
 * @property ReturnReason $reason
 * @property string|null $note
 * @property string $origin manual | sale_return | repair
 * @property string|null $origin_id
 * @property string|null $origin_label
 * @property string $status in_bin | on_note | settled
 * @property int|null $accepted_qty
 * @property string|null $outcome
 * @property string|null $created_by_name
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'branch_id', 'supplier_return_id', 'variant_id', 'variant_name', 'qty', 'serial', 'unit_cost', 'lot_id',
    'source_type', 'source_id', 'source_name', 'source_doc', 'detected_by', 'reason', 'note', 'origin', 'origin_id', 'origin_label',
    'status', 'accepted_qty', 'outcome', 'created_by_name', 'settled_at'])]
final class BinItem extends Model
{
    use BelongsToTenant, HasUuids;

    public const IN_BIN = 'in_bin';

    public const ON_NOTE = 'on_note';

    public const SETTLED = 'settled';

    protected $table = 'supplier_return_items';

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost' => 'integer',
            'accepted_qty' => 'integer',
            'source_type' => SourceType::class,
            'reason' => ReturnReason::class,
            'settled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ReturnNote, $this>
     */
    public function returnNote(): BelongsTo
    {
        return $this->belongsTo(ReturnNote::class, 'supplier_return_id');
    }

    public function value(): int
    {
        return $this->qty * $this->unit_cost;
    }

    /** @return array{type: string, id: string}|null */
    public function sourceKey(): ?array
    {
        return $this->source_type === null || $this->source_id === null ? null : ['type' => $this->source_type->value, 'id' => $this->source_id];
    }
}
