<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something the shop should know about (the bell). Seen by every user who has `permission`
 * (everyone when null); read state per user in notification_reads.
 *
 * @property string $id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $icon
 * @property string|null $to
 * @property string|null $permission
 * @property Carbon $created_at
 * @property int $seq
 */
#[Fillable(['tenant_id', 'type', 'title', 'body', 'icon', 'to', 'permission', 'created_at'])]
final class Notification extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'seq' => 'integer'];
    }

    /**
     * Only what this user may see.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, Authorizable $user): void
    {
        $allowed = self::query()->whereNotNull('permission')->distinct()->pluck('permission')
            ->filter(fn (string $permission): bool => $user->can($permission))
            ->values()->all();

        $query->where(fn (Builder $q) => $q->whereNull('permission')->orWhereIn('permission', $allowed));
    }

    /**
     * Not read by this user.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnreadBy(Builder $query, string $userId): void
    {
        $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notification_reads')
            ->whereColumn('notification_reads.notification_id', 'notifications.id')
            ->where('notification_reads.user_id', $userId));
    }
}
