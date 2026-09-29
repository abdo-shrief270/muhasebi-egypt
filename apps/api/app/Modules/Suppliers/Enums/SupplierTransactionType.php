<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Enums;

enum SupplierTransactionType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Payment = 'payment';
    case PurchaseReturn = 'purchase_return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'رصيد افتتاحي',
            self::Purchase => 'فاتورة شراء',
            self::Payment => 'دفعة',
            self::PurchaseReturn => 'مرتجع مشتريات',
        };
    }
}
