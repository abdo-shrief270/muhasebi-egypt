<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A technician's share of each device they repair: the parts that are set add up (a rate of 0
 * counts for nothing), e.g. 30% of the labor + 10% of the parts' price.
 *
 * @property int $id
 * @property string $user_id
 * @property int $labor_rate basis points of the labor after the discount
 * @property int $parts_rate basis points of the parts
 * @property string $parts_base price | profit (the parts' price less their cost)
 * @property int $profit_rate basis points of the whole repair's profit
 * @property int $fixed piasters per device
 * @property bool $warranty_clawback
 */
#[Fillable(['tenant_id', 'user_id', 'labor_rate', 'parts_rate', 'parts_base', 'profit_rate', 'fixed', 'warranty_clawback'])]
final class CommissionRule extends Model
{
    use BelongsToTenant;

    protected $table = 'repair_commission_rules';

    protected function casts(): array
    {
        return [
            'labor_rate' => 'integer',
            'parts_rate' => 'integer',
            'profit_rate' => 'integer',
            'fixed' => 'integer',
            'warranty_clawback' => 'boolean',
        ];
    }

    public function isEmpty(): bool
    {
        return $this->labor_rate === 0 && $this->parts_rate === 0 && $this->profit_rate === 0 && $this->fixed === 0;
    }

    /** "30% من المصنعية + 10% من سعر القطع" */
    public function describe(): string
    {
        $pct = fn (int $bp): string => rtrim(rtrim(number_format($bp / 100, 2), '0'), '.').'%';

        return implode(' + ', array_filter([
            $this->labor_rate ? $pct($this->labor_rate).' من المصنعية' : null,
            $this->parts_rate ? $pct($this->parts_rate).($this->parts_base === 'profit' ? ' من مكسب القطع' : ' من سعر القطع') : null,
            $this->profit_rate ? $pct($this->profit_rate).' من المكسب' : null,
            $this->fixed ? rtrim(rtrim(number_format($this->fixed / 100, 2), '0'), '.').' ج للجهاز' : null,
        ])) ?: 'من غير عمولة';
    }
}
