<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Models;

use App\Support\Modules\ModuleState;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether a shop may use an optional module (entitled, from billing) and whether it chose to (state).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $module_key
 * @property bool $entitled
 * @property ModuleState $state
 * @property string $source
 * @property CarbonImmutable|null $trial_started_at
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $enabled_at
 * @property CarbonImmutable|null $disabled_at
 */
#[Fillable(['tenant_id', 'module_key', 'entitled', 'state', 'source', 'trial_started_at', 'trial_ends_at', 'enabled_at', 'disabled_at'])]
final class TenantModule extends Model
{
    use BelongsToTenant;

    public const TRIAL_DAYS = 14;

    protected function casts(): array
    {
        return [
            'entitled' => 'boolean',
            'state' => ModuleState::class,
            'trial_started_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'enabled_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
        ];
    }

    /** The state after applying time (an expired trial without entitlement becomes read-only). */
    public function effectiveState(): ModuleState
    {
        if ($this->state === ModuleState::Trial && $this->trial_ends_at?->isPast()) {
            return $this->entitled ? ModuleState::Enabled : ModuleState::ReadOnly;
        }

        if ($this->state === ModuleState::Enabled && ! $this->entitled) {
            return ModuleState::ReadOnly;
        }

        return $this->state;
    }
}
