<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $variant_id
 * @property int $price the price the shop confirmed (a new price needs confirming again)
 */
#[Fillable(['tenant_id', 'variant_id', 'price'])]
final class MarketPriceConfirmation extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }
}
