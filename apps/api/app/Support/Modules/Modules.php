<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Facade;

/**
 * @method static ModuleState state(string $key, ?string $tenantId = null)
 * @method static bool enabled(string $key, ?string $tenantId = null)
 * @method static list<string> enabledKeys(?string $tenantId = null)
 * @method static void forget(string $tenantId)
 *
 * @see ModuleAccess
 */
final class Modules extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModuleAccess::class;
    }
}
