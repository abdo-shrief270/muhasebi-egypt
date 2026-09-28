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
 */
#[Fillable(['tenant_id', 'name', 'phone', 'email', 'password', 'is_owner'])]
#[Hidden(['password', 'pin_hash', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
