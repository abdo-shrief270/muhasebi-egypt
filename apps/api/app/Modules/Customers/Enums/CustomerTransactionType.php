<?php

declare(strict_types=1);

namespace App\Modules\Customers\Enums;

enum CustomerTransactionType: string
{
    case Opening = 'opening';
    case Sale = 'sale';
    case Repair = 'repair';
    case Payment = 'payment';
    case SaleReturn = 'sale_return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'رصيد افتتاحي',
            self::Sale => 'فاتورة آجل',
            self::Repair => 'صيانة آجل',
            self::Payment => 'تحصيل',
            self::SaleReturn => 'مرتجع',
        };
    }
}
