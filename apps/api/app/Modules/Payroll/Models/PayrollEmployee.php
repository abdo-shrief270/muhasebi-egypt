<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $user_id
 * @property string $pay_cycle monthly | weekly
 * @property int $base_salary piasters per period
 * @property bool $active
 */
#[Fillable(['tenant_id', 'user_id', 'pay_cycle', 'base_salary', 'active'])]
final class PayrollEmployee extends Model
{
    use BelongsToTenant;

    protected $attributes = ['pay_cycle' => 'monthly', 'base_salary' => 0, 'active' => true];

    protected function casts(): array
    {
        return ['base_salary' => 'integer', 'active' => 'boolean'];
    }
}
