<?php

declare(strict_types=1);

namespace App\Modules\Services\Enums;

enum Provider: string
{
    case Vodafone = 'vodafone';
    case Etisalat = 'etisalat';
    case Orange = 'orange';
    case We = 'we';
    case InstaPay = 'instapay';
    case Other = 'other';

    public function label(AccountKind $kind): string
    {
        return match ($kind) {
            AccountKind::Wallet => match ($this) {
                self::Vodafone => 'فودافون كاش',
                self::Etisalat => 'e& كاش',
                self::Orange => 'أورنج كاش',
                self::We => 'WE Pay',
                self::InstaPay => 'InstaPay',
                self::Other => 'محفظة تانية',
            },
            AccountKind::Airtime => match ($this) {
                self::Vodafone => 'رصيد فودافون',
                self::Etisalat => 'رصيد اتصالات',
                self::Orange => 'رصيد أورنج',
                self::We => 'رصيد WE',
                self::InstaPay, self::Other => 'رصيد تاني',
            },
        };
    }

    /**
     * @return list<self>
     */
    public static function for(AccountKind $kind): array
    {
        return $kind === AccountKind::Wallet ? self::cases() : [self::Vodafone, self::Etisalat, self::Orange, self::We, self::Other];
    }
}
