<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $used_device_id
 * @property string $kind id_front | id_back | device
 * @property string $path encrypted file on the local (private) disk
 * @property string $mime
 * @property int $size
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'used_device_id', 'kind', 'path', 'mime', 'size', 'created_at'])]
final class UsedDevicePhoto extends Model
{
    use BelongsToTenant;

    public const ID_KINDS = ['id_front', 'id_back'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'datetime'];
    }

    public function isIdCard(): bool
    {
        return in_array($this->kind, self::ID_KINDS, true);
    }
}
