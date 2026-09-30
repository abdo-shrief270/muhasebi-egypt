<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

enum MovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case RepairUse = 'repair_use';
    case RepairReturn = 'repair_return';
    case SupplierReturn = 'supplier_return';
    case UsedPurchase = 'used_purchase';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'رصيد افتتاحي',
            self::Purchase => 'شراء',
            self::Sale => 'بيع',
            self::SaleReturn => 'مرتجع بيع',
            self::Adjustment => 'تسوية / جرد',
            self::TransferIn => 'تحويل وارد',
            self::TransferOut => 'تحويل صادر',
            self::RepairUse => 'صرف لصيانة',
            self::RepairReturn => 'رجوع من صيانة',
            self::SupplierReturn => 'مرتجع لمورد',
            self::UsedPurchase => 'شراء مستعمل',
        };
    }
}
