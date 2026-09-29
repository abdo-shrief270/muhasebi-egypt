<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Wallet = 'wallet';
    case InstaPay = 'instapay';

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
