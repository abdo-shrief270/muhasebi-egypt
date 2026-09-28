<?php

declare(strict_types=1);

namespace App\Support\Events;

use ReflectionClass;

/**
 * Something that happened in a module. Recorded in the outbox (domain_events) inside the
 * same transaction as the change, then published to listeners by the relay.
 *
 * Subclasses declare NAME ("module.something_happened"), bump VERSION on breaking payload
 * changes, and take only scalar/array constructor properties so they serialize cleanly.
 */
abstract class DomainEvent
{
    public const NAME = '';

    public const VERSION = 1;

    private ?string $eventId = null;

    abstract public function tenantId(): ?string;

    public function eventId(): ?string
    {
        return $this->eventId;
    }

    public function withEventId(string $eventId): static
    {
        $copy = clone $this;
        $copy->eventId = $eventId;

        return $copy;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [];

        foreach ((new ReflectionClass($this))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $payload[$parameter->getName()] = $this->{$parameter->getName()};
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new static(...$payload);
    }
}
