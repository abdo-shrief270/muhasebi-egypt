<?php

declare(strict_types=1);

namespace App\Modules\Cash\Contracts;

/** Why money moved through a drawer, for the modules that record it via CashDrawer. */
enum DrawerEntry: string
{
    case Sale = 'sale';
    case SaleRefund = 'sale_refund';
    case CustomerPayment = 'customer_payment';
    /** A repair deposit, the final bill, or a deposit refunded (negative). */
    case Repair = 'repair';
    /** Money paid to a supplier out of the drawer (negative). */
    case SupplierPayment = 'supplier_payment';
    /** Wallet transfers and airtime top-ups (Services): cash taken or handed out at the counter, funding a wallet from the drawer or cashing one out into it. */
    case Service = 'service';
    /** Money a supplier paid back for returned goods (positive). */
    case SupplierRefund = 'supplier_refund';
    /** A used device bought from a walk-in seller, paid out of the drawer (negative). */
    case UsedDevicePurchase = 'used_device_purchase';
    /** The delivery fee of an online order, collected with its invoice (positive). */
    case Delivery = 'delivery';
    /** Cash paid to an import contact (supplier, shipping, customs…) out of the drawer (negative); its reversal puts it back. */
    case ImportPayment = 'import_payment';
}
