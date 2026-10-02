<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Something happened (a sale, a return, a shift…): the owner's live screens refresh. */
final class ShopActivity implements ShouldBroadcast
{
    public function __construct(public readonly string $tenantId, public readonly string $kind) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenants.{$this->tenantId}.owner");
    }

    public function broadcastAs(): string
    {
        return 'activity';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind];
    }
}
