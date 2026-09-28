<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One line in a shop's "سجل العمليات": who did what, when, from where.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string|null $user_id
 * @property string $user_name
 * @property string $action
 * @property string $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 */
#[Table('audit_log', timestamps: false)]
#[Fillable(['tenant_id', 'user_id', 'user_name', 'action', 'description', 'subject_type', 'subject_id', 'properties', 'ip', 'created_at'])]
final class AuditEntry extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
