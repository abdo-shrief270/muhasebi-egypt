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

    /**
     * Replaces $text with $replacement in the descriptions of $subject's entries — for erasing a
     * person's name while keeping the record of what was done.
     */
    public function redact(Model $subject, string $text, string $replacement): void
    {
        if ($text === '' || $text === $replacement) {
            return;
        }

        AuditEntry::query()
            ->where('subject_type', class_basename($subject))
            ->where('subject_id', (string) $subject->getKey())
            ->get()
            ->filter(fn (AuditEntry $entry) => str_contains($entry->description, $text))
            ->each(fn (AuditEntry $entry) => $entry->forceFill(['description' => str_replace($text, $replacement, $entry->description)])->save());
    }
}
