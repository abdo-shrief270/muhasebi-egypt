<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How one user wants the «ابدأ من هنا» card: folded, or hidden for good.
 *
 * @property string $user_id
 * @property string $tenant_id
 * @property bool $collapsed
 * @property Carbon|null $dismissed_at
 */
#[Fillable(['user_id', 'tenant_id', 'collapsed', 'dismissed_at'])]
final class OnboardingPreference extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $attributes = ['collapsed' => false];

    protected function casts(): array
    {
        return ['collapsed' => 'boolean', 'dismissed_at' => 'datetime'];
    }
}
