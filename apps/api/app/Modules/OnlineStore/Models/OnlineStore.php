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
 * @property string $mode off | whatsapp | orders
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
 * @property Carbon $updated_at
 */
#[Fillable([
    'tenant_id', 'slug', 'mode', 'name', 'tagline', 'about', 'color', 'branch_id', 'whatsapp', 'phone', 'address',
    'map_url', 'hours', 'policy', 'facebook', 'instagram', 'show_out_of_stock', 'show_quantity', 'logo', 'cover',
])]
final class OnlineStore extends Model
{
    use BelongsToTenant, HasUuids;

    public const MODES = ['off', 'whatsapp'];

    protected function casts(): array
    {
        return ['show_out_of_stock' => 'boolean', 'show_quantity' => 'boolean'];
    }

    public function isOpen(): bool
    {
        return $this->mode !== 'off';
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
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
