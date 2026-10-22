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
 * @property string $kind advance | bonus | deduction
 * @property int $amount
 * @property string|null $note
 * @property Carbon $on_date
 * @property string|null $paid_from
 * @property string|null $payslip_id
 * @property string|null $created_by_name
 */
#[Fillable(['tenant_id', 'user_id', 'kind', 'amount', 'note', 'on_date', 'paid_from', 'branch_id', 'payslip_id', 'created_by_name'])]
final class PayrollAdjustment extends Model
{
    use BelongsToTenant, HasUuids;

    public const KINDS = ['advance' => 'سلفة', 'bonus' => 'مكافأة', 'deduction' => 'خصم'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'on_date' => 'date'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'kind' => $this->kind,
            'kind_label' => self::KINDS[$this->kind] ?? $this->kind,
            'amount' => $this->amount,
            'note' => $this->note,
            'on_date' => $this->on_date->toDateString(),
            'paid_from' => $this->paid_from,
            'settled' => $this->payslip_id !== null,
            'created_by_name' => $this->created_by_name,
        ];
    }
}
