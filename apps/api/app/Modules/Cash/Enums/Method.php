<?php

declare(strict_types=1);

namespace App\Modules\Cash\Enums;

/** How money was taken. Same values as the sales and customer payment methods. */
enum Method: string
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
