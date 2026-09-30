<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something a user told us from inside the app (a problem, a suggestion, a question).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $user_id
 * @property string|null $user_name
 * @property string $type
 * @property string $message
 * @property string|null $page
 * @property string|null $app_version
 * @property string|null $user_agent
 * @property string|null $screen
 * @property string|null $screenshot_path
 * @property string $status
 * @property Carbon|null $status_changed_at
 * @property string|null $status_changed_by
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'user_id', 'user_name', 'type', 'message', 'page', 'app_version', 'user_agent', 'screen', 'screenshot_path', 'status', 'status_changed_at', 'status_changed_by'])]
final class Feedback extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'feedback';

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime'];
    }
}
