<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $key
 * @property string $body
 * @property string|null $updated_by_name
 * @property Carbon $updated_at
 */
#[Fillable(['tenant_id', 'key', 'body', 'updated_by_name'])]
final class MessageTemplate extends Model
{
    use BelongsToTenant, HasUuids;
}
