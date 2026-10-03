<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Enums;

/**
 * new → confirmed → preparing → out_for_delivery (delivery) / ready (pickup) → delivered, which
 * only the invoice does («حوّل لفاتورة»: stock and money go the usual way). Cancelled from any
 * open state, with a reason the customer sees.
 */
enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Confirmed => 'اتأكد',
            self::Preparing => 'بيتجهّز',
            self::OutForDelivery => 'خرج للتوصيل',
            self::Ready => 'جاهز للاستلام',
            self::Delivered => 'اتسلّم',
            self::Cancelled => 'اتلغى',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Delivered && $this !== self::Cancelled;
    }

    /**
     * Where the shop can move it by hand (delivered only comes with the invoice).
     *
     * @return list<self>
     */
    public function next(bool $delivery): array
    {
        return match ($this) {
            self::New => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [$delivery ? self::OutForDelivery : self::Ready, self::Cancelled],
            self::OutForDelivery, self::Ready => [self::Cancelled],
            default => [],
        };
    }
}
