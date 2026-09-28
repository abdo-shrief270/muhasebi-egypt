<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum CategoryType: string
{
    case Accessory = 'accessory';
    case Part = 'part';
    case Device = 'device';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Accessory => 'إكسسوارات',
            self::Part => 'قطع غيار',
            self::Device => 'أجهزة',
            self::Other => 'أخرى',
        };
    }
}
