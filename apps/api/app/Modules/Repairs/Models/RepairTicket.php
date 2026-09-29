<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Modules\Repairs\Enums\TicketStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A device left for repair.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property int $number
 * @property TicketStatus $status
 * @property string $customer_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property int|null $device_model_id
 * @property string $device_name
 * @property string|null $imei
 * @property string|null $color
 * @property string $unlock_type
 * @property string|null $unlock_code
 * @property list<string> $accessories
 * @property list<string> $condition
 * @property array<string, string> $checks
 * @property list<array{id: int, category: string, name: string}> $reported_faults
 * @property string|null $reported_note
 * @property list<array{id: int, category: string, name: string}>|null $diagnosed_faults
 * @property string|null $diagnosis_note
 * @property string|null $received_by_name
 * @property Carbon $received_at
 * @property Carbon|null $expected_at
 * @property string|null $technician_id
 * @property string|null $technician_name
 * @property Carbon|null $ready_at
 * @property Carbon|null $delivered_at
 * @property string|null $delivered_by_name
 * @property int|null $estimate
 * @property int $labor
 * @property int $parts_total
 * @property int $parts_cost
 * @property int $discount
 * @property int $total
 * @property int $paid
 * @property int $credit
 * @property int $commission the technician's, fixed at delivery
 * @property string|null $commission_rule
 * @property int $warranty_days
 * @property Carbon|null $warranty_until
 * @property string|null $warranty_of_id
 * @property string $public_token
 * @property string|null $outsourced_order_id sent to a partner shop for repair (its shop order)
 * @property string|null $outsourced_reference
 * @property string|null $outsourced_shop
 * @property string|null $outsourced_status the shop order's status
 * @property int $outsource_cost the partner's price, a cost of this ticket
 * @property string|null $partner_order_id taken in from a partner shop's repair order
 * @property int|null $partner_item_id
 * @property string|null $partner_reference
 */
#[Fillable([
    'id', 'tenant_id', 'branch_id', 'number', 'status', 'customer_id', 'customer_name', 'customer_phone',
    'device_model_id', 'device_name', 'imei', 'color', 'unlock_type', 'unlock_code', 'accessories', 'condition', 'checks',
    'reported_faults', 'reported_note', 'diagnosed_faults', 'diagnosis_note',
    'received_by', 'received_by_name', 'received_at', 'expected_at', 'technician_id', 'technician_name',
    'ready_at', 'delivered_at', 'delivered_by', 'delivered_by_name',
    'estimate', 'labor', 'parts_total', 'parts_cost', 'discount', 'total', 'paid', 'credit', 'commission', 'commission_rule',
    'warranty_days', 'warranty_until', 'warranty_of_id', 'public_token',
    'outsourced_order_id', 'outsourced_reference', 'outsourced_shop', 'outsourced_status', 'outsource_cost',
    'partner_order_id', 'partner_item_id', 'partner_reference',
])]
final class RepairTicket extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = [
        'labor' => 0,
        'parts_total' => 0,
        'parts_cost' => 0,
        'discount' => 0,
        'total' => 0,
        'paid' => 0,
        'credit' => 0,
        'commission' => 0,
        'outsource_cost' => 0,
        'warranty_days' => 0,
        'unlock_type' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'number' => 'integer',
            'device_model_id' => 'integer',
            'unlock_code' => 'encrypted',
            'accessories' => 'array',
            'condition' => 'array',
            'checks' => 'array',
            'reported_faults' => 'array',
            'diagnosed_faults' => 'array',
            'received_at' => 'datetime',
            'expected_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'warranty_until' => 'datetime',
            'estimate' => 'integer',
            'labor' => 'integer',
            'parts_total' => 'integer',
            'parts_cost' => 'integer',
            'outsource_cost' => 'integer',
            'partner_item_id' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'credit' => 'integer',
            'commission' => 'integer',
            'warranty_days' => 'integer',
        ];
    }

    public function reference(): string
    {
        return 'RP-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /** What's still owed: the bill less what was paid and what went on the account. Negative = to refund. */
    public function due(): int
    {
        return $this->total - $this->paid - $this->credit;
    }

    /** Recomputes the bill from labor, parts and discount (call after changing any of them). */
    public function recalculate(): void
    {
        $parts = $this->parts()->get();
        $this->parts_total = (int) $parts->sum(fn (RepairTicketPart $p) => $p->qty * $p->unit_price);
        $this->parts_cost = (int) $parts->sum(fn (RepairTicketPart $p) => $p->qty * $p->unit_cost);
        $this->total = max(0, $this->labor + $this->parts_total - $this->discount);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->status !== TicketStatus::Ready && $this->status !== TicketStatus::Rejected
            && $this->expected_at !== null && $this->expected_at->isPast();
    }

    public function underWarranty(): bool
    {
        return $this->status === TicketStatus::Delivered && $this->warranty_until !== null && $this->warranty_until->isFuture();
    }

    /**
     * @return HasMany<RepairTicketPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(RepairTicketPart::class, 'ticket_id')->orderBy('id');
    }

    /**
     * @return HasMany<RepairTicketPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(RepairTicketPayment::class, 'ticket_id')->orderBy('id');
    }

    /**
     * @return HasMany<RepairTicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RepairTicketEvent::class, 'ticket_id')->orderBy('seq');
    }

    /** What the repair cost the shop: parts from stock and a partner shop's price. */
    public function cost(): int
    {
        return $this->parts_cost + $this->outsource_cost;
    }

    /** Sent to a partner and not back or called off yet. */
    public function isOutsourced(): bool
    {
        return $this->outsourced_order_id !== null && ! in_array($this->outsourced_status, ['completed', 'rejected', 'cancelled'], true);
    }
}
