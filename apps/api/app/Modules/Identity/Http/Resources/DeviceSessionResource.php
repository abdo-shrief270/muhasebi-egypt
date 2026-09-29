<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A signed-in device (a Sanctum token). Pass the current token id to mark "this device".
 *
 * @mixin PersonalAccessToken
 */
final class DeviceSessionResource extends JsonResource
{
    public ?int $currentId = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'device_name' => $this->name,
            'ip_address' => $this->getAttribute('ip_address'),
            'user_agent' => $this->getAttribute('user_agent'),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'current' => $this->currentId !== null && (int) $this->id === $this->currentId,
        ];
    }
}
