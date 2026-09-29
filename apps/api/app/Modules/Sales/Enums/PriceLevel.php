<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum PriceLevel: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'قطاعي',
            self::Wholesale => 'جملة',
            self::Technician => 'فني',
        };
    }
}
