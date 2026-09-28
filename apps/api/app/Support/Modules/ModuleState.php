<?php

declare(strict_types=1);

namespace App\Support\Modules;

enum ModuleState: string
{
    case NotEntitled = 'not_entitled';
    case Trial = 'trial';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case ReadOnly = 'read_only';

    public function isUsable(): bool
    {
        return $this === self::Trial || $this === self::Enabled;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotEntitled => 'غير مشترك',
            self::Trial => 'تجربة',
            self::Enabled => 'مفعّل',
            self::Disabled => 'مخفي',
            self::ReadOnly => 'قراءة فقط',
        };
    }
}
