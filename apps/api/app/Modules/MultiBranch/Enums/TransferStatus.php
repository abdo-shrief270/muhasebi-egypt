<?php

declare(strict_types=1);

namespace App\Modules\MultiBranch\Enums;

enum TransferStatus: string
{
    case Requested = 'requested';
    case Shipped = 'shipped';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'مطلوب',
            self::Shipped => 'في الطريق',
            self::Received => 'اتستلم',
            self::Cancelled => 'اتلغى',
        };
    }
}
