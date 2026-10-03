<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Actions;

use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Support\OrderTimeline;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/** The shop moves an order along (confirm, prepare, out / ready) or cancels it with a reason. */
final class MoveOrderAction
{
    public function __construct(
        private readonly OrderTimeline $timeline,
        private readonly Auditor $audit,
    ) {}

    public function handle(OnlineOrder $order, OrderStatus $to, ?string $reason = null): OnlineOrder
    {
        return DB::transaction(function () use ($order, $to, $reason): OnlineOrder {
            $order = OnlineOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($to, $order->status->next($order->isDelivery()), true)) {
                throw new DomainRuleException(
                    $to === OrderStatus::Delivered
                        ? 'الطلب بيتقفل لما تعمله فاتورة («حوّل لفاتورة»).'
                        : "الطلب {$order->reference()} {$order->status->label()}، مينفعش يبقى «{$to->label()}».",
                    'online_order_status_invalid',
                );
            }
            $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            if ($to === OrderStatus::Cancelled && $reason === null) {
                throw new DomainRuleException('اكتب سبب الإلغاء (الزبون هيشوفه).', 'cancel_reason_required');
            }

            $order->update(['status' => $to, 'cancel_reason' => $to === OrderStatus::Cancelled ? $reason : $order->cancel_reason]);
            $this->timeline->add($order, $to, $reason);
            if ($to === OrderStatus::Cancelled) {
                $this->audit->record('online_store.order_cancelled', "لغى الطلب الأونلاين {$order->reference()}: {$reason}", $order);
            }

            return $order;
        });
    }
}
