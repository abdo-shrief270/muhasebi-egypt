<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $credential_id
 * @property string $public_key
 * @property int $alg
 * @property int $sign_count
 * @property string $name
 * @property Carbon|null $last_used_at
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'user_id', 'credential_id', 'public_key', 'alg', 'sign_count', 'name', 'last_used_at'])]
#[Hidden(['public_key'])]
final class Passkey extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['alg' => 'integer', 'sign_count' => 'integer', 'last_used_at' => 'datetime'];
    }

    /**
     * @return array{id: string, credential_id: string, name: string, created_at: string, last_used_at: string|null}
     */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'credential_id' => $this->credential_id,
            'name' => $this->name,
            'created_at' => $this->created_at->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
