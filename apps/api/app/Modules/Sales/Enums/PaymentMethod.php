<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Wallet = 'wallet';
    case InstaPay = 'instapay';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::Card => 'فيزا',
            self::Wallet => 'محفظة',
            self::InstaPay => 'InstaPay',
        };
    }
}
