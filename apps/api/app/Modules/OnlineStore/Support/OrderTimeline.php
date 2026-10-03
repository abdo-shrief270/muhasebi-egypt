<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\OnlineOrderEvent;
use Illuminate\Contracts\Auth\Factory as Auth;

/** Writes an order's timeline, stamped with who did it (none = the customer, on the store). */
final class OrderTimeline
{
    public function __construct(private readonly Auth $auth) {}

    public function add(OnlineOrder $order, OrderStatus $status, ?string $note = null): void
    {
        OnlineOrderEvent::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'status' => $status,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
            'user_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
