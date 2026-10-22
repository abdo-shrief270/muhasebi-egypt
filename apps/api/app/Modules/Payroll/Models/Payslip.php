<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $user_name
 * @property string $cycle
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int $base_salary
 * @property int $tickets
 * @property int $commission
 * @property int $clawback
 * @property int $bonuses
 * @property int $deductions
 * @property int $advances
 * @property int $net
 * @property Carbon|null $paid_at
 * @property string|null $paid_from
 * @property string|null $paid_by_name
 */
#[Fillable(['tenant_id', 'user_id', 'user_name', 'cycle', 'starts_on', 'ends_on', 'base_salary', 'tickets', 'commission', 'clawback', 'bonuses', 'deductions', 'advances', 'net', 'paid_at', 'paid_from', 'paid_by_name', 'closed_by_name'])]
final class Payslip extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'payroll_payslips';

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'paid_at' => 'datetime',
            'base_salary' => 'integer', 'tickets' => 'integer', 'commission' => 'integer', 'clawback' => 'integer',
            'bonuses' => 'integer', 'deductions' => 'integer', 'advances' => 'integer', 'net' => 'integer',
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user_name,
            'cycle' => $this->cycle,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'base_salary' => $this->base_salary,
            'tickets' => $this->tickets,
            'commission' => $this->commission,
            'clawback' => $this->clawback,
            'bonuses' => $this->bonuses,
            'deductions' => $this->deductions,
            'advances' => $this->advances,
            'net' => $this->net,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_from' => $this->paid_from,
            'paid_by_name' => $this->paid_by_name,
            'closed' => true,
        ];
    }
}
