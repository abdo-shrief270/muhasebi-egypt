<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records sensitive actions for the shop owner to review. Call it from actions, inside their transaction.
 */
final class Auditor
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?string $tenantId = null): AuditEntry
    {
        $user = $this->auth->guard('sanctum')->user();

        return AuditEntry::create([
            'tenant_id' => $tenantId ?? $subject?->getAttribute('tenant_id'),
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name') ?? 'النظام',
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            'properties' => $properties ?: null,
            'ip' => $this->request->ip(),
            'created_at' => now(),
        ]);
    }
}
