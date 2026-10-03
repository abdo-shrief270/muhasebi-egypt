<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Modules\OnlineStore\Support\StoreMedia;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $slug
 * @property string $mode off | whatsapp (the cart goes as a WhatsApp message) | orders (saved in the app)
 * @property string $name
 * @property string|null $tagline
 * @property string|null $about
 * @property string $color
 * @property string|null $branch_id
 * @property string|null $whatsapp E.164
 * @property string|null $phone E.164
 * @property string|null $address
 * @property string|null $map_url
 * @property string|null $hours
 * @property string|null $policy
 * @property string|null $facebook
 * @property string|null $instagram
 * @property bool $show_out_of_stock
 * @property bool $show_quantity
 * @property string|null $logo
 * @property string|null $cover
 * @property bool $pickup
 * @property bool $delivery
 * @property int $min_order piasters, 0 = none
 * @property int|null $free_delivery_over piasters
 * @property bool $pay_cod
 * @property bool $pay_transfer
 * @property string|null $transfer_instapay InstaPay address / phone
 * @property string|null $transfer_wallet wallet number (E.164)
 * @property Carbon $updated_at
 */
#[Fillable([
    'tenant_id', 'slug', 'mode', 'name', 'tagline', 'about', 'color', 'branch_id', 'whatsapp', 'phone', 'address',
    'map_url', 'hours', 'policy', 'facebook', 'instagram', 'show_out_of_stock', 'show_quantity', 'logo', 'cover',
    'pickup', 'delivery', 'min_order', 'free_delivery_over', 'pay_cod', 'pay_transfer', 'transfer_instapay', 'transfer_wallet',
])]
final class OnlineStore extends Model
{
    use BelongsToTenant, HasUuids;

    public const MODES = ['off', 'whatsapp', 'orders'];

    protected function casts(): array
    {
        return [
            'show_out_of_stock' => 'boolean',
            'show_quantity' => 'boolean',
            'pickup' => 'boolean',
            'delivery' => 'boolean',
            'min_order' => 'integer',
            'free_delivery_over' => 'integer',
            'pay_cod' => 'boolean',
            'pay_transfer' => 'boolean',
        ];
    }

    public function isOpen(): bool
    {
        return $this->mode !== 'off';
    }

    public function takesOrders(): bool
    {
        return $this->mode === 'orders';
    }

    /**
     * What the public store shows about the shop (never the branch id or internal fields).
     *
     * @return array<string, mixed>
     */
    public function toPublic(): array
    {
        return [
            'slug' => $this->slug,
            'mode' => $this->mode,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'about' => $this->about,
            'color' => $this->color,
            'whatsapp' => $this->whatsapp,
            'phone' => $this->phone,
            'address' => $this->address,
            'map_url' => $this->map_url,
            'hours' => $this->hours,
            'policy' => $this->policy,
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'show_quantity' => $this->show_quantity,
            'logo' => StoreMedia::urls($this->tenant_id, 'logo', $this->logo),
            'cover' => StoreMedia::urls($this->tenant_id, 'cover', $this->cover),
            'ordering' => $this->takesOrders() ? [
                'pickup' => $this->pickup,
                'delivery' => $this->delivery,
                'min_order' => $this->min_order,
                'free_delivery_over' => $this->free_delivery_over,
                'pay_cod' => $this->pay_cod,
                'pay_transfer' => $this->pay_transfer,
                'transfer_instapay' => $this->pay_transfer ? $this->transfer_instapay : null,
                'transfer_wallet' => $this->pay_transfer ? $this->transfer_wallet : null,
            ] : null,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
