<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'مكتملة',
            self::PartiallyRefunded => 'فيها مرتجع',
            self::Refunded => 'مرتجعة بالكامل',
        };
    }
}
