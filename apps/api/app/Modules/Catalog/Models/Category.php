<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\CategoryType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property CategoryType $type
 * @property int $sort
 */
#[Fillable(['tenant_id', 'name', 'type', 'sort'])]
final class Category extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
