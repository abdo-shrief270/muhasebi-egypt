<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Contracts;

/** What a cashier may need the owner's (or a manager's) OK for, each with its own switch and limit. */
enum ApprovalKind: string
{
    /** A discount above the owner's percentage (the measure: percent of the sale before discounts). */
    case Discount = 'discount';
    /** Selling below cost (the measure: 1). */
    case BelowCost = 'below_cost';
    /** A sales return above the owner's amount (the measure: piasters refunded). */
    case Return = 'return';
    /** Cash taken out of the drawer (withdrawal or expense) above the owner's amount (piasters). */
    case Withdrawal = 'withdrawal';
    /** A credit (آجل) sale past the customer's limit (the measure: piasters over the limit). */
    case CreditLimit = 'credit_limit';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'خصم كبير',
            self::BelowCost => 'بيع بخسارة',
            self::Return => 'مرتجع كبير',
            self::Withdrawal => 'سحب أو مصروف كبير من الدرج',
            self::CreditLimit => 'آجل فوق حد العميل',
        };
    }

    /** The owner's switch (owner_app manifest). */
    public function feature(): string
    {
        return 'owner_app.approve_'.$this->value;
    }
}
