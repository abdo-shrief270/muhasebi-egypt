<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Support\FeeRule;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $account_id
 * @property OperationType $operation
 * @property int $percent basis points
 * @property int $fixed
 * @property int $min
 * @property int|null $max
 * @property int $round_to
 */
#[Fillable(['tenant_id', 'account_id', 'operation', 'percent', 'fixed', 'min', 'max', 'round_to'])]
final class ServiceFeeRule extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'operation' => OperationType::class,
            'percent' => 'integer',
            'fixed' => 'integer',
            'min' => 'integer',
            'max' => 'integer',
            'round_to' => 'integer',
        ];
    }

    public function rule(): FeeRule
    {
        return new FeeRule($this->percent, $this->fixed, $this->min, $this->max, $this->round_to);
    }
}
