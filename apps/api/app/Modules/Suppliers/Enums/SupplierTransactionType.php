<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Enums;

enum SupplierTransactionType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Payment = 'payment';
    case PurchaseReturn = 'purchase_return';
    /** Returned goods (a return note) the supplier accepted: we owe less. */
    case ReturnNote = 'return_note';
    /** Money the supplier paid back to us: we owe more (or they owe less). */
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'رصيد افتتاحي',
            self::Purchase => 'فاتورة شراء',
            self::Payment => 'دفعة',
            self::PurchaseReturn => 'مرتجع مشتريات',
            self::ReturnNote => 'إذن مرتجع',
            self::Refund => 'فلوس رجعت من المورد',
        };
    }
}
