<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Wallet = 'wallet';
    case InstaPay = 'instapay';

    /** The drawer method this is paid from; a bank transfer never touches a drawer. */
    public function drawerMethod(): ?string
    {
        return $this === self::BankTransfer ? null : $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::BankTransfer => 'تحويل بنكي',
            self::Wallet => 'محفظة',
            self::InstaPay => 'InstaPay',
        };
    }
}
