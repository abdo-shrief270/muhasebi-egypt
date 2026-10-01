<?php

declare(strict_types=1);

namespace App\Modules\Installments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One dated installment of a plan.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property int $seq
 * @property Carbon $due_on
 * @property int $amount
 * @property int $paid
 * @property Carbon|null $paid_at
 * @property-read InstallmentPlan $plan
 */
#[Fillable(['tenant_id', 'plan_id', 'seq', 'due_on', 'amount', 'paid', 'paid_at'])]
final class InstallmentItem extends Model
{
    use BelongsToTenant;

    protected $attributes = ['paid' => 0];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'due_on' => 'date',
            'amount' => 'integer',
            'paid' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function remaining(): int
    {
        return $this->amount - $this->paid;
    }

    /** Days past its date and not paid in full (0 when not late). */
    public function daysLate(?Carbon $today = null): int
    {
        $today ??= Carbon::today();

        return $this->remaining() > 0 && $this->due_on->lt($today) ? (int) $this->due_on->diffInDays($today) : 0;
    }

    /** @return BelongsTo<InstallmentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'plan_id');
    }
}
