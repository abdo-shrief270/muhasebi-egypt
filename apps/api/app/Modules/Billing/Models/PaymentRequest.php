<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $plan
 * @property string $cycle
 * @property list<string> $modules
 * @property int $amount
 * @property string $method
 * @property string $reference
 * @property string|null $sender_name
 * @property string|null $sender_phone
 * @property string|null $proof_path
 * @property string $status pending | approved | rejected | cancelled
 * @property string|null $requested_by_name
 * @property string|null $reviewed_by_name
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $invoice_id
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'plan', 'cycle', 'modules', 'amount', 'method', 'reference', 'sender_name', 'sender_phone', 'proof_path',
    'status', 'requested_by', 'requested_by_name', 'reviewed_by', 'reviewed_by_name', 'reviewed_at', 'rejection_reason', 'invoice_id',
])]
final class PaymentRequest extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['modules' => 'array', 'amount' => 'integer', 'reviewed_at' => 'datetime'];
    }
}
