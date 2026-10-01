<?php

declare(strict_types=1);

namespace App\Modules\Installments\Models;

use App\Modules\Installments\Enums\PlanStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property int $number
 * @property string $customer_id
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string|null $sale_id
 * @property string|null $sale_reference
 * @property int $principal
 * @property int $markup
 * @property int|null $markup_rate
 * @property int $total
 * @property int $paid
 * @property int $count
 * @property int $interval_months
 * @property Carbon $first_due_on
 * @property string|null $guarantor_name
 * @property string|null $guarantor_phone
 * @property string|null $notes
 * @property PlanStatus $status
 * @property string|null $created_by
 * @property string|null $created_by_name
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancelled_by_name
 * @property Carbon $created_at
 * @property-read Collection<int, InstallmentItem> $items
 * @property-read Collection<int, InstallmentPayment> $payments
 */
#[Fillable([
    'tenant_id', 'branch_id', 'number', 'customer_id', 'customer_name', 'customer_phone', 'sale_id', 'sale_reference',
    'principal', 'markup', 'markup_rate', 'total', 'paid', 'count', 'interval_months', 'first_due_on',
    'guarantor_name', 'guarantor_phone', 'notes', 'status', 'created_by', 'created_by_name',
    'completed_at', 'cancelled_at', 'cancelled_by_name',
])]
final class InstallmentPlan extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['paid' => 0, 'markup' => 0, 'status' => 'active', 'interval_months' => 1];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'principal' => 'integer',
            'markup' => 'integer',
            'markup_rate' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'count' => 'integer',
            'interval_months' => 'integer',
            'first_due_on' => 'date',
            'status' => PlanStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return sprintf('INS-%05d', $this->number);
    }

    public function remaining(): int
    {
        return $this->total - $this->paid;
    }

    /** What the plan still accounts for on the customer's balance (nothing once it's cancelled). */
    public function outstanding(): int
    {
        return $this->status === PlanStatus::Active ? $this->remaining() : 0;
    }

    /** @return HasMany<InstallmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InstallmentItem::class, 'plan_id')->orderBy('seq');
    }

    /** @return HasMany<InstallmentPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class, 'plan_id')->orderBy('seq');
    }
}
