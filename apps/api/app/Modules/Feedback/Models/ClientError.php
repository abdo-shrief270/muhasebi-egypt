<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An uncaught error in a shop's browser, deduplicated per shop by fingerprint and counted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $fingerprint
 * @property string $kind
 * @property string $message
 * @property string|null $source
 * @property string|null $stack
 * @property string|null $page
 * @property string|null $app_version
 * @property string|null $user_agent
 * @property string|null $last_user_id
 * @property int $count
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 */
#[Fillable(['tenant_id', 'fingerprint', 'kind', 'message', 'source', 'stack', 'page', 'app_version', 'user_agent', 'last_user_id', 'count', 'first_seen_at', 'last_seen_at', 'resolved_at'])]
final class ClientError extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
