<?php

declare(strict_types=1);

namespace App\Modules\Services\Enums;

/** What moved an account's balance. */
enum OperationType: string
{
    case Opening = 'opening';
    /** إيداع: the customer pays cash, the shop sends from its wallet. */
    case Deposit = 'deposit';
    /** سحب: the customer sends to the shop's wallet and takes cash. */
    case Withdraw = 'withdraw';
    /** شحن رصيد: the customer pays, the shop tops up their line from its airtime balance. */
    case Topup = 'topup';
    /** تمويل: money into the account from the drawer or the safe (airtime bought from a distributor). */
    case Fund = 'fund';
    /** تسييل: money out of the account into the drawer or the safe. */
    case CashOut = 'cash_out';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'رصيد افتتاحي',
            self::Deposit => 'إيداع',
            self::Withdraw => 'سحب',
            self::Topup => 'شحن رصيد',
            self::Fund => 'تمويل',
            self::CashOut => 'تسييل',
        };
    }

    /** Done for a customer at the counter (earns a fee). */
    public function isCustomer(): bool
    {
        return in_array($this, [self::Deposit, self::Withdraw, self::Topup], true);
    }

    /**
     * @return list<self>
     */
    public static function customer(): array
    {
        return [self::Deposit, self::Withdraw, self::Topup];
    }
}
