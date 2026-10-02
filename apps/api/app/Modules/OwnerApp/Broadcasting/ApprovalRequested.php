<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A request waiting on whoever may approve it (their open screens update at once). */
final class ApprovalRequested implements ShouldBroadcast
{
    /**
     * @param  array<string, mixed>  $approval
     */
    public function __construct(public readonly string $tenantId, public readonly array $approval) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenants.{$this->tenantId}.approvals");
    }

    public function broadcastAs(): string
    {
        return 'approval.requested';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['approval' => $this->approval];
    }
}
