<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One thing a platform admin did. Platform-wide (not a shop's), append-only.
 *
 * @property string $id
 * @property string|null $admin_id
 * @property string|null $admin_name
 * @property string $action
 * @property string|null $tenant_id
 * @property string|null $subject_id
 * @property array<string, mixed>|null $details
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable(['admin_id', 'admin_name', 'action', 'tenant_id', 'subject_id', 'details', 'ip', 'user_agent', 'created_at'])]
final class PlatformAdminAction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Admin actions are append-only.'));
        self::deleting(fn () => throw new LogicException('Admin actions are append-only.'));
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
