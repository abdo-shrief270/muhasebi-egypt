<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Enums\Governorate;

/** Egypt's governorates by key, for other modules that show or filter by where a branch is. */
final class Governorates
{
    /** @return array<string, string> key => Arabic name, in the usual order */
    public static function labels(): array
    {
        $labels = [];
        foreach (Governorate::cases() as $g) {
            $labels[$g->value] = $g->label();
        }

        return $labels;
    }
}
