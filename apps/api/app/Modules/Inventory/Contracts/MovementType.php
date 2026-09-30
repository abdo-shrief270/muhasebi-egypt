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
    /** Set aside in the returns bin (supplier returns): out of sellable stock. */
    case ReturnsBin = 'returns_bin';
    /** Back from the returns bin into sellable stock (the supplier refused it, or it was put there by mistake). */
    case ReturnsBinBack = 'returns_bin_back';
    /** A supplier replaced returned units. */
    case SupplierReplacement = 'supplier_replace';
    /** A damaged unit written off (serials only: the stock left when it was set aside). */
    case WriteOff = 'write_off';

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
            self::ReturnsBin => 'لسلة المرتجعات',
            self::ReturnsBinBack => 'رجوع من سلة المرتجعات',
            self::SupplierReplacement => 'بديل من المورد',
            self::WriteOff => 'إعدام تالف',
        };
    }
}
