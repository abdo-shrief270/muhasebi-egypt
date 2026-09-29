<?php

declare(strict_types=1);

namespace App\Modules\Cash\Enums;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Salaries = 'salaries';
    case Transport = 'transport';
    case Hospitality = 'hospitality';
    case Internet = 'internet';
    case Maintenance = 'maintenance';
    case Supplies = 'supplies';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'إيجار',
            self::Utilities => 'كهربا ومية',
            self::Salaries => 'مرتبات وسُلف',
            self::Transport => 'نقل ومواصلات',
            self::Hospitality => 'بوفيه وضيافة',
            self::Internet => 'نت وتليفون',
            self::Maintenance => 'صيانة المحل',
            self::Supplies => 'أدوات ومستلزمات',
            self::Other => 'أخرى',
        };
    }
}
