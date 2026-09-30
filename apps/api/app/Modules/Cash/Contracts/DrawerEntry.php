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
    /** Money a supplier paid back for returned goods (positive). */
    case SupplierRefund = 'supplier_refund';
}
