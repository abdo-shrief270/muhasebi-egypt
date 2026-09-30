<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone who sold the shop a device. The national ID is encrypted; national_id_hash finds the
 * same person again. After erasure the name and phone are gone but the ID stays for the legal
 * retention period (UsedDeviceSetting), then it and the card photos are purged.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $national_id
 * @property string|null $national_id_hash
 * @property Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $governorate
 * @property Carbon|null $erased_at
 * @property Carbon|null $id_purged_at
 * @property-read Collection<int, UsedDevice> $devices
 */
#[Fillable(['tenant_id', 'name', 'phone', 'national_id', 'national_id_hash', 'birth_date', 'gender', 'governorate', 'erased_at', 'id_purged_at'])]
final class UsedDeviceSeller extends Model
{
    use BelongsToTenant, HasUuids;

    protected $hidden = ['national_id', 'national_id_hash'];

    protected function casts(): array
    {
        return [
            'national_id' => 'encrypted',
            'birth_date' => 'date',
            'erased_at' => 'datetime',
            'id_purged_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<UsedDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UsedDevice::class, 'seller_id');
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }
}
