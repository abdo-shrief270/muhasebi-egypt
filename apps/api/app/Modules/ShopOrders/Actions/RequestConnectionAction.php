<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Actions;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\ShopOrders\Enums\ConnectionStatus;
use App\Modules\ShopOrders\Events\ShopConnectionRequested;
use App\Modules\ShopOrders\Models\ShopConnection;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use Illuminate\Support\Facades\DB;

/**
 * Ask another shop (found by its code) to become a partner. If they already asked us, it's accepted.
 */
final class RequestConnectionAction
{
    public function __construct(
        private readonly ShopDirectory $directory,
        private readonly ModuleAccess $modules,
        private readonly EventRecorder $events,
    ) {}

    public function handle(string $tenantId, string $userId, string $code): ShopConnection
    {
        $other = $this->directory->findByCode($code)
            ?? throw new DomainRuleException('مفيش محل بالكود ده.', 'shop_not_found', 404);

        if ($other->id === $tenantId) {
            throw new DomainRuleException('ده كود محلك انت.', 'cannot_connect_to_self');
        }

        if (! $this->modules->enabled('shop_orders', $other->id)) {
            throw new DomainRuleException("محل «{$other->name}» مش مفعّل الطلبات بين المحلات.", 'partner_module_disabled');
        }

        return DB::transaction(function () use ($tenantId, $userId, $other): ShopConnection {
            $existing = ShopConnection::between($tenantId, $other->id);

            if ($existing?->status === ConnectionStatus::Accepted) {
                throw new DomainRuleException("انتو شركاء بالفعل مع «{$other->name}».", 'already_partners');
            }

            if ($existing?->status === ConnectionStatus::Pending) {
                if ($existing->requester_tenant_id === $tenantId) {
                    throw new DomainRuleException('الطلب اتبعت قبل كده ومستني موافقتهم.', 'already_requested');
                }

                // They asked us first: asking back means yes.
                $existing->update(['status' => ConnectionStatus::Accepted, 'responded_at' => now()]);

                return $existing;
            }

            $connection = $existing ?? new ShopConnection;
            $connection->fill([
                'requester_tenant_id' => $tenantId,
                'addressee_tenant_id' => $other->id,
                'status' => ConnectionStatus::Pending,
                'requested_by' => $userId,
                'responded_at' => null,
            ])->save();

            $this->events->record(new ShopConnectionRequested($other->id, $connection->id, $tenantId));

            return $connection;
        });
    }
}
