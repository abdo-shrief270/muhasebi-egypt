<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Contracts\TechnicianPricing;
use App\Support\Modules\FeatureAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

final class PartPricing implements TechnicianPricing
{
    public const FEATURE = 'repairs.part_requests';

    public function __construct(private readonly FeatureAccess $features) {}

    public function on(): bool
    {
        return $this->features->enabled(self::FEATURE);
    }

    public function technicianOnly(?Authenticatable $user): bool
    {
        if (! $user instanceof Authorizable || ! $user->can('repairs.request_parts')) {
            return false;
        }
        foreach (['repairs.hand_parts', 'sales.sell', 'products.manage'] as $wider) {
            if ($user->can($wider)) {
                return false;
            }
        }

        return $this->on();
    }

    public function price(int $retail, ?int $technician): int
    {
        return $technician ?? $retail;
    }
}
