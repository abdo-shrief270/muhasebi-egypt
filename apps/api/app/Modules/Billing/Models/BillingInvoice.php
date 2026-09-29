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
 * @property int $number
 * @property string $plan
 * @property string $cycle
 * @property int $months
 * @property list<array{description: string, amount: int}> $lines
 * @property int $total
 * @property int $vat
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $method
 * @property string|null $payment_reference
 * @property string|null $issued_by_name
 * @property string|null $note
 * @property Carbon $paid_at
 */
#[Fillable(['tenant_id', 'number', 'plan', 'cycle', 'months', 'lines', 'total', 'vat', 'period_start', 'period_end', 'method', 'payment_reference', 'issued_by_name', 'note', 'paid_at'])]
final class BillingInvoice extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'number' => 'integer',
            'months' => 'integer',
            'total' => 'integer',
            'vat' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'INV-'.$this->paid_at->format('Y').'-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}
