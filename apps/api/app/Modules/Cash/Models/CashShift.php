<?php

declare(strict_types=1);

namespace App\Modules\Cash\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property int $number
 * @property string $user_id
 * @property string $user_name
 * @property int $opening_cash
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $closed_by_name
 * @property array<string, int>|null $expected
 * @property array<string, int>|null $counted
 * @property int|null $cash_difference
 * @property string|null $note
 */
#[Fillable(['tenant_id', 'branch_id', 'number', 'user_id', 'user_name', 'opening_cash', 'opened_at', 'closed_at', 'closed_by', 'closed_by_name', 'expected', 'counted', 'cash_difference', 'note'])]
final class CashShift extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'opening_cash' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'expected' => 'array',
            'counted' => 'array',
            'cash_difference' => 'integer',
        ];
    }

    public function reference(): string
    {
        return 'SH-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }
}
