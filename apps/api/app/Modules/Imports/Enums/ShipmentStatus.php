<?php

declare(strict_types=1);

namespace App\Modules\Imports\Enums;

/** ordered → shipped → customs → arrived (moved by hand) → received (into stock) | cancelled. */
enum ShipmentStatus: string
{
    case Ordered = 'ordered';
    case Shipped = 'shipped';
    case Customs = 'customs';
    case Arrived = 'arrived';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'تم الطلب',
            self::Shipped => 'اتشحنت',
            self::Customs => 'في الجمارك',
            self::Arrived => 'وصلت',
            self::Received => 'اتستلمت في المخزن',
            self::Cancelled => 'اتلغت',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Received && $this !== self::Cancelled;
    }

    /** The manual steps on the way (receiving and cancelling have their own actions). */
    public static function onTheWay(): array
    {
        return [self::Ordered, self::Shipped, self::Customs, self::Arrived];
    }
}
