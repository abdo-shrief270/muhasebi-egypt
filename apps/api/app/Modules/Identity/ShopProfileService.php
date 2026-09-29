<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\ShopProfile;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;

final class ShopProfileService implements ShopProfile
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function types(): array
    {
        $tenant = Tenant::query()->findOrFail($this->tenant->idOrFail());

        return array_map(fn (ShopType $t) => $t->value, $tenant->types());
    }
}
