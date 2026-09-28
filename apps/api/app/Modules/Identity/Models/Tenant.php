<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\ShopType;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop (the SaaS customer). Platform-level: not tenant scoped itself.
 *
 * @property string $id
 * @property string $name
 * @property string $phone
 * @property ShopType $shop_type
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['name', 'phone', 'shop_type', 'settings'])]
#[UseFactory(TenantFactory::class)]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'shop_type' => ShopType::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
