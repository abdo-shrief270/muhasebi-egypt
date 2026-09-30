<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Enums;

enum Grade: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'زي الجديد',
            self::B => 'حالة كويسة',
            self::C => 'فيه عيوب',
        };
    }
}
