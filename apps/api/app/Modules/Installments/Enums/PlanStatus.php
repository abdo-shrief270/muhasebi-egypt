<?php

declare(strict_types=1);

namespace App\Modules\Installments\Enums;

enum PlanStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'شغّال',
            self::Completed => 'خلص',
            self::Cancelled => 'اتلغى',
        };
    }
}
