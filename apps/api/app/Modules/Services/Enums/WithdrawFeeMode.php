<?php

declare(strict_types=1);

namespace App\Modules\Services\Enums;

/** How the customer pays the fee on a withdrawal. */
enum WithdrawFeeMode: string
{
    /** The customer sends the amount and gets it back in cash less the fee. */
    case Cash = 'cash';
    /** The customer sends the amount plus the fee and gets the whole amount in cash. */
    case Wallet = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'العمولة تتخصم من الكاش اللي بياخده',
            self::Wallet => 'العميل يحوّل العمولة مع المبلغ',
        };
    }
}
