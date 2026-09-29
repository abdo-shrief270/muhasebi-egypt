<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum AdjustmentReason: string
{
    case Count = 'count';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Gift = 'gift';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Count => 'جرد',
            self::Damaged => 'تالف',
            self::Lost => 'فقد / سرقة',
            self::Gift => 'هدية / عينة',
            self::Correction => 'تصحيح خطأ',
        };
    }
}
