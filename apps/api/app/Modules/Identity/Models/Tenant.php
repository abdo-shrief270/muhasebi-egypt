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
 * @property ShopType $shop_type
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['name', 'code', 'phone', 'shop_type', 'settings'])]
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
