<?php

declare(strict_types=1);

namespace App\Modules\Services\Enums;

/** Where funding comes from / cashing out goes to. */
enum MoneySource: string
{
    /** The user's shift drawer (recorded through CashDrawer). */
    case Drawer = 'drawer';
    /** The safe / the owner's pocket: not a drawer, nothing is recorded in a shift. */
    case Safe = 'safe';

    public function label(): string
    {
        return match ($this) {
            self::Drawer => 'الدرج',
            self::Safe => 'الخزنة',
        };
    }
}
