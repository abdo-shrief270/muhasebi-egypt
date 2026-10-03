<?php

declare(strict_types=1);

namespace App\Modules\Imports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone an importer deals with — factory / trader, buying agent, shipping company, customs
 * broker. Just a contact (no account); `balance` > 0 = the shop owes them, in EGP piasters.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $type supplier | agent | shipping | customs
 * @property string $name
 * @property string|null $country
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $wechat
 * @property string|null $whatsapp
 * @property string|null $notes
 * @property int $balance
 * @property bool $is_active
 */
#[Fillable(['tenant_id', 'type', 'name', 'country', 'city', 'phone', 'wechat', 'whatsapp', 'notes', 'balance', 'is_active'])]
final class ImportContact extends Model
{
    use BelongsToTenant, HasUuids;

    public const TYPES = ['supplier' => 'مصنع / تاجر', 'agent' => 'وسيط', 'shipping' => 'شركة شحن', 'customs' => 'مخلّص جمركي'];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => self::TYPES[$this->type] ?? $this->type,
            'name' => $this->name,
            'country' => $this->country,
            'city' => $this->city,
            'phone' => $this->phone,
            'wechat' => $this->wechat,
            'whatsapp' => $this->whatsapp,
            'notes' => $this->notes,
            'balance' => $this->balance,
            'is_active' => $this->is_active,
        ];
    }
}
