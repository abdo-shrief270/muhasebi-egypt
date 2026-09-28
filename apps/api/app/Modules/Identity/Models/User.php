<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Users are resolved before a tenant is known (login, token lookup), so this model is
 * deliberately NOT tenant scoped. Always constrain by tenant_id when listing users.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property bool $is_owner
 * @property bool $is_active
 * @property int|null $role_id
 */
#[Fillable(['tenant_id', 'name', 'phone', 'email', 'password', 'is_owner', 'role_id', 'is_active'])]
#[Hidden(['password', 'pin_hash', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /** Mirror the column defaults so freshly created models are complete (strict mode). */
    protected $attributes = [
        'is_owner' => false,
        'is_active' => true,
        'role_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Branches a non-owner may work in. Owners may work in every branch.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }
}
