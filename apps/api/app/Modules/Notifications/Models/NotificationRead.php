<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A user has read a notification.
 *
 * @property string $notification_id
 * @property string $user_id
 * @property Carbon $read_at
 */
#[Fillable(['notification_id', 'user_id', 'tenant_id', 'read_at'])]
final class NotificationRead extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'notification_id';

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
