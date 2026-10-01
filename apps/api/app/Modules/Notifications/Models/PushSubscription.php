<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser / installed app that gets a user's push notifications.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property string|null $device
 * @property Carbon|null $last_sent_at
 */
#[Fillable(['tenant_id', 'user_id', 'endpoint', 'p256dh', 'auth', 'device', 'last_sent_at'])]
final class PushSubscription extends Model
{
    use BelongsToTenant;

    protected $hidden = ['p256dh', 'auth'];

    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }
}
