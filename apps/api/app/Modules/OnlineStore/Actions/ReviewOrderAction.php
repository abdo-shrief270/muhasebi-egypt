<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Actions;

use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\OnlineReview;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/** The customer rates the shop from the order's tracking page, once, after it was delivered. */
final class ReviewOrderAction
{
    public function handle(OnlineOrder $order, int $rating, ?string $comment): OnlineReview
    {
        return DB::transaction(function () use ($order, $rating, $comment): OnlineReview {
            $order = OnlineOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->review()->exists()) {
                throw new DomainRuleException('قيّمت الطلب ده قبل كده. شكراً!', 'already_reviewed', 409);
            }
            if (! $order->canBeReviewed()) {
                throw new DomainRuleException('التقييم بيبقى بعد ما الطلب يتسلّم (لحد 30 يوم).', 'review_not_allowed', 409);
            }
            $comment = $comment !== null ? (trim($comment) ?: null) : null;

            return OnlineReview::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'rating' => $rating,
                'comment' => $comment !== null ? mb_substr($comment, 0, 500) : null,
                // The first name only, the way it shows.
                'customer_name' => mb_substr(explode(' ', trim($order->customer_name))[0] ?: 'زبون', 0, 60),
                'source' => $order->source,
            ]);
        });
    }
}
