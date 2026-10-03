<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A shipment's paper: invoice, packing list, bill of lading, photos. On the private disk.
 *
 * @property string $id
 * @property string $shipment_id
 * @property string $kind
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string|null $uploaded_by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'shipment_id', 'kind', 'name', 'path', 'mime', 'size', 'uploaded_by_name', 'created_at'])]
final class ImportAttachment extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    public const KINDS = ['invoice' => 'فاتورة', 'packing' => 'قايمة التعبئة', 'bill_of_lading' => 'بوليصة الشحن', 'photo' => 'صور البضاعة', 'other' => 'مرفق'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'datetime'];
    }
}
