<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Console;

use App\Modules\OnlineStore\Contracts\ShopReputation;
use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Modules\OnlineStore\Models\OnlineCoupon;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Support\OrderTimeline;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('online-store:expire-unanswered')]
#[Description('Cancel «سوق محاسبي» orders the shop never answered in time (the customer then sees other shops)')]
final class ExpireUnansweredCommand extends Command
{
    public const REASON = 'المحل ما ردّش على الطلب في الوقت. تقدر تطلب نفس الحاجات من محل تاني.';

    public function handle(ShopReputation $reputation, OrderTimeline $timeline): int
    {
        $minutes = $reputation->answerMinutes();
        $expired = 0;
        OnlineOrder::withoutTenancy()->where('source', 'market')->where('status', OrderStatus::New)
            ->where('created_at', '<', now()->subMinutes($minutes))->orderBy('created_at')->limit(500)->pluck('id')
            ->each(function (string $id) use ($timeline, &$expired): void {
                DB::transaction(function () use ($id, $timeline, &$expired): void {
                    $order = OnlineOrder::withoutTenancy()->lockForUpdate()->find($id);
                    if ($order === null || $order->status !== OrderStatus::New) {
                        return; // answered meanwhile
                    }
                    $order->update(['status' => OrderStatus::Cancelled, 'cancel_reason' => self::REASON, 'auto_cancelled' => true]);
                    $timeline->add($order, OrderStatus::Cancelled, self::REASON);
                    if ($order->coupon_id !== null) {
                        OnlineCoupon::withoutTenancy()->whereKey($order->coupon_id)->where('uses', '>', 0)->decrement('uses');
                    }
                    $expired++;
                });
            });
        $this->line("{$expired} unanswered order(s) cancelled.");

        return self::SUCCESS;
    }
}
