<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money paid to an import contact (EGP), for a shipment or on account.
 *
 * @property string $id
 * @property string $contact_id
 * @property string|null $shipment_id
 * @property int $amount
 * @property string $method
 * @property Carbon $paid_on
 * @property string|null $received_by
 * @property string|null $reference
 * @property string|null $proof
 * @property string|null $note
 * @property string|null $user_name
 * @property Carbon|null $reversed_at
 * @property string|null $branch_id the branch whose drawer it came out of
 * @property bool $from_drawer cash out of the payer's shift drawer
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'contact_id', 'shipment_id', 'amount', 'method', 'paid_on', 'received_by', 'reference', 'proof', 'note', 'user_name', 'reversed_at', 'branch_id', 'from_drawer'])]
final class ImportPayment extends Model
{
    use BelongsToTenant, HasUuids;

    public const METHODS = ['bank' => 'تحويل بنكي', 'exchange' => 'شركة تحويل', 'agent' => 'عن طريق وسيط', 'cash' => 'كاش', 'wallet' => 'محفظة'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'date', 'reversed_at' => 'datetime', 'from_drawer' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'shipment_id' => $this->shipment_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'method_label' => self::METHODS[$this->method] ?? $this->method,
            'paid_on' => $this->paid_on->toDateString(),
            'received_by' => $this->received_by,
            'reference' => $this->reference,
            'has_proof' => $this->proof !== null,
            'note' => $this->note,
            'user_name' => $this->user_name,
            'reversed' => $this->reversed_at !== null,
            'from_drawer' => $this->from_drawer,
        ];
    }
}
