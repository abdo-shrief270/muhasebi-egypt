<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop's word on which catalog phone one of its variants is (phone_id null = none of them).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $variant_id
 * @property string|null $phone_id
 * @property int|null $storage_gb
 */
#[Fillable(['tenant_id', 'variant_id', 'phone_id', 'storage_gb'])]
final class MarketLink extends Model
{
    use BelongsToTenant;

    protected $table = 'market_links';

    protected function casts(): array
    {
        return ['storage_gb' => 'integer'];
    }
}
