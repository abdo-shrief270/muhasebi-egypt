<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Enums;

enum ConnectionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'مستني الموافقة',
            self::Accepted => 'شريك',
            self::Declined => 'مرفوض',
        };
    }
}
