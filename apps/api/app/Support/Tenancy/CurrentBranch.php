<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * The branch the current request works in (from the X-Branch-Id header, resolved by the `branch` middleware).
 */
final class CurrentBranch
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

    public function idOrFail(): string
    {
        return $this->id ?? throw new \LogicException('No branch is set for the current request.');
    }
}
