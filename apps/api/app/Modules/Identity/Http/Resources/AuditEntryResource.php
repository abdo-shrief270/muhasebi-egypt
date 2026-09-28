<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Support\Audit\AuditEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditEntry
 */
final class AuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'user_name' => $this->user_name,
            'subject_type' => $this->subject_type,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
