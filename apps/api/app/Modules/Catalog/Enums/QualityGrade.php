<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * How spare parts and some accessories are graded in the Egyptian market.
 */
enum QualityGrade: string
{
    case Original = 'original';
    case ServicePack = 'service_pack';
    case HighCopy = 'high_copy';
    case Copy = 'copy';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'أصلي',
            self::ServicePack => 'سيرفس باك',
            self::HighCopy => 'هاي كوبي',
            self::Copy => 'كوبي',
        };
    }
}
