<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** The answer, to the cashier waiting for it — and to the other approvers, so it leaves their lists. */
final class ApprovalDecided implements ShouldBroadcast
{
    /**
     * @param  array<string, mixed>  $approval
     */
    public function __construct(public readonly string $tenantId, public readonly string $requesterId, public readonly array $approval) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->requesterId}"), new PrivateChannel("tenants.{$this->tenantId}.approvals")];
    }

    public function broadcastAs(): string
    {
        return 'approval.decided';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['approval' => $this->approval];
    }
}
