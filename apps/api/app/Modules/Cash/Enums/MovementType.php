<?php

declare(strict_types=1);

namespace App\Modules\Cash\Enums;

enum MovementType: string
{
    case Sale = 'sale';
    case SaleRefund = 'sale_refund';
    case CustomerPayment = 'customer_payment';
    case Expense = 'expense';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'مبيعات',
            self::SaleRefund => 'مرتجع مبيعات',
            self::CustomerPayment => 'تحصيل من عميل',
            self::Expense => 'مصروف',
            self::Deposit => 'إيداع',
            self::Withdrawal => 'سحب',
        };
    }

    /** Movements typed in on the cash screen (the rest come from sales and customers). */
    public function isManual(): bool
    {
        return in_array($this, [self::Expense, self::Deposit, self::Withdrawal], true);
    }
}
