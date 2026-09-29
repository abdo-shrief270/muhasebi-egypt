<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $user_id
 * @property string $type percent | fixed
 * @property int $value basis points, or piasters per device
 * @property string $base labor | profit
 */
#[Fillable(['tenant_id', 'user_id', 'type', 'value', 'base'])]
final class CommissionRule extends Model
{
    use BelongsToTenant;

    protected $table = 'repair_commission_rules';

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /** "15% من المصنعية" / "50 ج للجهاز" */
    public function describe(): string
    {
        return $this->type === 'fixed'
            ? number_format($this->value / 100, 2).' ج للجهاز'
            : rtrim(rtrim(number_format($this->value / 100, 2), '0'), '.').'% من '.($this->base === 'profit' ? 'المكسب' : 'المصنعية');
    }
}
