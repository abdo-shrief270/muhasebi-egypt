<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'طلب جديد',
            self::Accepted => 'اتقبل',
            self::Preparing => 'جاري التجهيز',
            self::Ready => 'جاهز',
            self::Delivered => 'اتسلّم للمشتري',
            self::Completed => 'تم',
            self::Rejected => 'مرفوض',
            self::Cancelled => 'ملغي',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /**
     * Who may move the order from this status to which statuses.
     *
     * @return list<self>
     */
    public function nextFor(Party $party): array
    {
        return match ([$this, $party]) {
            [self::Placed, Party::Seller] => [self::Accepted, self::Rejected],
            [self::Placed, Party::Buyer] => [self::Cancelled],
            [self::Accepted, Party::Seller] => [self::Preparing, self::Cancelled],
            [self::Accepted, Party::Buyer] => [self::Cancelled],
            [self::Preparing, Party::Seller] => [self::Ready, self::Cancelled],
            [self::Ready, Party::Seller] => [self::Delivered],
            [self::Delivered, Party::Buyer] => [self::Completed],
            default => [],
        };
    }

    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Accepted => 'accepted_at',
            self::Ready => 'ready_at',
            self::Delivered => 'delivered_at',
            self::Completed => 'completed_at',
            self::Rejected, self::Cancelled => 'closed_at',
            default => null,
        };
    }
}
