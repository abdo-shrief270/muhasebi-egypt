<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Models;

use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer's device: each repair ticket for it points here (`repair_tickets.device_id`).
 *
 * @property string $id
 * @property string $customer_id
 * @property int|null $device_model_id
 * @property string $name
 * @property string|null $imei
 * @property string|null $color
 * @property string|null $notes
 * @property Carbon|null $last_seen_at
 */
#[Fillable(['tenant_id', 'customer_id', 'device_model_id', 'name', 'imei', 'color', 'notes', 'last_seen_at'])]
final class RepairDevice extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'device_model_id' => 'integer'];
    }

    /** @return HasMany<RepairTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(RepairTicket::class, 'device_id');
    }

    /**
     * The device this intake is for: the one picked (it must be the customer's), else the
     * customer's with the same IMEI, else with the same model (or name) and colour, else a new one.
     *
     * @param  array<string, mixed>  $data
     */
    public static function forIntake(string $tenantId, string $customerId, array $data): self
    {
        $query = self::query()->where('customer_id', $customerId);
        if (! empty($data['device_id'])) {
            $device = (clone $query)->find($data['device_id'])
                ?? throw new DomainRuleException('الجهاز ده مش بتاع العميل ده.', 'device_not_found', 404);
        } else {
            $imei = $data['imei'] ?? null;
            $device = $imei ? (clone $query)->where('imei', $imei)->first() : null;
            $device ??= (clone $query)
                ->where(fn ($q) => isset($data['device_model_id'])
                    ? $q->where('device_model_id', $data['device_model_id'])
                    : $q->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $data['device_name']))]))
                ->whereRaw("lower(coalesce(color, '')) = ?", [mb_strtolower(trim((string) ($data['color'] ?? '')))])
                ->when($imei, fn ($q) => $q->whereNull('imei'))
                ->first();
        }
        $device ??= new self(['tenant_id' => $tenantId, 'customer_id' => $customerId, 'name' => $data['device_name']]);
        $device->fill(array_filter([
            'device_model_id' => $data['device_model_id'] ?? null,
            'imei' => $data['imei'] ?? null,
            'color' => $data['color'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        $device->last_seen_at = now();
        $device->save();

        return $device;
    }
}
