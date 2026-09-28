<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions inside one shop (كاشير، فني…). The owner doesn't need a role.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string|null $key
 * @property string $name
 * @property list<string> $permissions
 */
#[Fillable(['tenant_id', 'key', 'name', 'permissions'])]
final class Role extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
