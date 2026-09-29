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
 * @property string $code short public code other shops use to find this shop
 * @property string $phone
 * @property ShopType $shop_type the first of shop_types
 * @property list<string>|null $shop_types
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['name', 'code', 'phone', 'shop_type', 'shop_types', 'settings'])]
#[UseFactory(TenantFactory::class)]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    /** No 0/O/1/I so codes can be read over the phone. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        self::creating(function (Tenant $tenant): void {
            $tenant->code ??= self::newCode();
        });
    }

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    protected function casts(): array
    {
        return [
            'shop_type' => ShopType::class,
            'shop_types' => 'array',
            'settings' => 'array',
        ];
    }

    /**
     * What the shop does (one or more).
     *
     * @return list<ShopType>
     */
    public function types(): array
    {
        $stored = array_values(array_filter(array_map(fn ($v) => ShopType::tryFrom((string) $v), $this->shop_types ?? [])));

        return $stored !== [] ? $stored : $this->shop_type->parts();
    }

    /**
     * @param  list<ShopType>  $types
     */
    public function setTypes(array $types): void
    {
        $this->shop_types = array_values(array_unique(array_map(fn (ShopType $t) => $t->value, $types)));
        $this->shop_type = $types[0];
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
