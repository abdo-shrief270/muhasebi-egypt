<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $template
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $phone
 * @property string|null $user_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'template', 'subject_type', 'subject_id', 'phone', 'user_id', 'user_name', 'created_at'])]
final class MessageLog extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Message logs are append-only.'));
        self::deleting(fn () => throw new LogicException('Message logs are append-only.'));
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
