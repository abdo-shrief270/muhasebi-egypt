<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A repair booked on the online store (BK-00001): the shop calls the customer, and when the device
 * arrives the intake turns it into a repair ticket.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $number
 * @property string $status new | contacted | converted | cancelled
 * @property string|null $customer_id
 * @property string $customer_name
 * @property string $customer_phone E.164
 * @property string $device
 * @property string $problem
 * @property Carbon|null $preferred_on
 * @property bool|null $consent
 * @property string|null $ticket_id
 * @property string|null $ticket_reference
 * @property string|null $cancel_reason
 * @property string|null $handled_by_name
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'number', 'status', 'customer_id', 'customer_name', 'customer_phone', 'device', 'problem', 'preferred_on',
    'consent', 'ticket_id', 'ticket_reference', 'cancel_reason', 'handled_by_name',
])]
final class RepairBooking extends Model
{
    use BelongsToTenant, HasUuids;

    public const PREFIX = 'BK';

    public const STATUSES = ['new' => 'جديد', 'contacted' => 'اتكلمنا معاه', 'converted' => 'اتعملت تذكرة', 'cancelled' => 'اتلغى'];

    /** Still waiting for the device. */
    public const OPEN = ['new', 'contacted'];

    protected $table = 'online_repair_bookings';

    protected function casts(): array
    {
        return ['number' => 'integer', 'preferred_on' => 'date', 'consent' => 'boolean'];
    }

    public function reference(): string
    {
        return self::PREFIX.'-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'device' => $this->device,
            'problem' => $this->problem,
            'preferred_on' => $this->preferred_on?->toDateString(),
            'consent' => $this->consent,
            'ticket_id' => $this->ticket_id,
            'ticket_reference' => $this->ticket_reference,
            'cancel_reason' => $this->cancel_reason,
            'handled_by_name' => $this->handled_by_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
