<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Enums;

/** How the seller was paid. Only cash comes out of the drawer. */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Wallet = 'wallet';
    case InstaPay = 'instapay';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش من الدرج',
            self::Wallet => 'محفظة',
            self::InstaPay => 'InstaPay',
            self::Bank => 'تحويل بنكي',
        };
    }
}
