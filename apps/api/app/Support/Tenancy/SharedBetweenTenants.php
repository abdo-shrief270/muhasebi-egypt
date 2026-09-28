<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * For records that belong to two shops at once (e.g. an order between a buyer and a seller shop).
 * A shop sees the row when it is any of the parties; with no tenant in context it sees nothing.
 *
 * @mixin Model
 */
trait SharedBetweenTenants
{
    /**
     * Columns holding the tenant ids of the parties.
     *
     * @return list<string>
     */
    abstract public static function tenantColumns(): array;

    protected static function bootSharedBetweenTenants(): void
    {
        static::addGlobalScope(new SharedTenantScope);
    }

    /**
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        return static::query()->withoutGlobalScope(SharedTenantScope::class);
    }
}
