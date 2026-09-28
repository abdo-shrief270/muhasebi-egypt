<?php

declare(strict_types=1);

namespace App\Support\Modules;

enum ModuleTier: string
{
    /** Always on, never shown to the shop as a module (identity, billing, sync…). */
    case Platform = 'platform';

    /** Included in every subscription. */
    case Core = 'core';

    /** Enabled per shop according to its subscription. */
    case Optional = 'optional';

    public function isAlwaysOn(): bool
    {
        return $this !== self::Optional;
    }
}
