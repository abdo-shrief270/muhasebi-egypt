<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Modules\Imports\Enums\ShipmentStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An import shipment (IMP-00001) from a supplier, received into a branch at its landed cost.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $number
 * @property string $contact_id
 * @property string $branch_id
 * @property ShipmentStatus $status
 * @property Carbon $ordered_on
 * @property Carbon|null $expected_on
 * @property string $allocation value | qty | weight
 * @property string|null $original_amount
 * @property int $goods_total
 * @property int $costs_total
 * @property string|null $notes
 * @property Carbon|null $received_at
 * @property string|null $received_by_name
 * @property string|null $cancel_reason
 * @property Carbon|null $late_alerted_for the expected date the «late» alert went out for
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'number', 'contact_id', 'branch_id', 'status', 'ordered_on', 'expected_on', 'allocation', 'original_amount',
    'goods_total', 'costs_total', 'notes', 'received_at', 'received_by_name', 'cancel_reason', 'late_alerted_for',
])]
final class ImportShipment extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'number' => 'integer',
            'ordered_on' => 'date',
            'expected_on' => 'date',
            'goods_total' => 'integer',
            'costs_total' => 'integer',
            'received_at' => 'datetime',
            'late_alerted_for' => 'date',
        ];
    }

    /** @return BelongsTo<ImportContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ImportContact::class, 'contact_id');
    }

    /** @return HasMany<ImportShipmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ImportShipmentItem::class, 'shipment_id');
    }

    /** @return HasMany<ImportShipmentCost, $this> */
    public function costs(): HasMany
    {
        return $this->hasMany(ImportShipmentCost::class, 'shipment_id')->orderBy('created_at');
    }

    /** @return HasMany<ImportPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ImportPayment::class, 'shipment_id')->orderByDesc('paid_on');
    }

    /** @return HasMany<ImportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ImportAttachment::class, 'shipment_id')->orderBy('created_at');
    }

    public function reference(): string
    {
        return 'IMP-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }
}
