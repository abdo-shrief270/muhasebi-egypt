<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Actions;

use App\Modules\ShopOrders\Enums\ConnectionStatus;
use App\Modules\ShopOrders\Models\ShopConnection;
use App\Support\Exceptions\DomainRuleException;

final class RespondToConnectionAction
{
    public function handle(string $tenantId, string $connectionId, bool $accept): ShopConnection
    {
        $connection = ShopConnection::query()->findOrFail($connectionId);

        if ($connection->addressee_tenant_id !== $tenantId || $connection->status !== ConnectionStatus::Pending) {
            throw new DomainRuleException('الطلب ده مش مستني ردّك.', 'connection_not_pending');
        }

        $connection->update([
            'status' => $accept ? ConnectionStatus::Accepted : ConnectionStatus::Declined,
            'responded_at' => now(),
        ]);

        return $connection;
    }
}
