<?php

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A row in the outbox. Platform-level (not tenant scoped): the relay reads across tenants.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property int $version
 * @property class-string<DomainEvent> $event_class
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 * @property Carbon|null $published_at
 * @property int $attempts
 */
#[Table('domain_events', timestamps: false)]
#[Fillable(['tenant_id', 'name', 'version', 'event_class', 'payload', 'occurred_at', 'published_at', 'attempts'])]
final class StoredEvent extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'version' => 'integer',
            'attempts' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function toDomainEvent(): DomainEvent
    {
        $class = $this->event_class;

        return $class::fromPayload($this->payload)->withEventId($this->id);
    }
}
