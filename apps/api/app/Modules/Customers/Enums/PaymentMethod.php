<?php

declare(strict_types=1);

namespace App\Modules\Customers\Enums;

/** How a customer paid off their account. Same values as the sales payment methods. */
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
