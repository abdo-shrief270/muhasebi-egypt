<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Closure;

/**
 * The tenant (shop) the current request / job is running for.
 * Registered as a scoped singleton so it resets between Octane requests and queue jobs.
 */
final class CurrentTenant
{
    private ?string $id = null;

    public function set(?string $id): void
    {
        $this->id = $id;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function check(): bool
    {
        return $this->id !== null;
    }

    public function idOrFail(): string
    {
        return $this->id ?? throw new MissingTenantException;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(?string $id, Closure $callback): mixed
    {
        $previous = $this->id;
        $this->id = $id;

        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
