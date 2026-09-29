<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Models\PlatformAdminAction;
use Illuminate\Http\Request;

/** Writes the platform admins' own log (sign-ins, approvals, activations, suspensions…). */
final class AdminLog
{
    public function __construct(private readonly Request $request) {}

    /** @param array<string, mixed> $details */
    public function record(string $action, ?string $tenantId = null, ?string $subjectId = null, array $details = [], ?PlatformAdmin $admin = null): void
    {
        $admin ??= $this->request->user() instanceof PlatformAdmin ? $this->request->user() : null;

        PlatformAdminAction::create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'tenant_id' => $tenantId,
            'subject_id' => $subjectId,
            'details' => $details ?: null,
            'ip' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
