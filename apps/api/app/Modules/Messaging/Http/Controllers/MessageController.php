<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Modules\Messaging\Models\MessageLog;
use App\Modules\Messaging\Models\MessageTemplate;
use App\Modules\Messaging\Support\Templates;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** WhatsApp message templates (the shop's wording over the built-in one) and the log of messages opened. */
final class MessageController
{
    public const SUBJECTS = ['repair_ticket', 'sale', 'customer', 'supplier_return'];

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Auditor $audit,
    ) {}

    public function templates(): JsonResponse
    {
        $custom = MessageTemplate::query()->get()->keyBy('key');

        return response()->json(['data' => collect(Templates::all())->map(fn (array $t, string $key): array => [
            'key' => $key,
            'label' => $t['label'],
            'group' => $t['group'],
            'group_label' => Templates::groupLabel($t['group']),
            'variables' => array_map(fn (string $name, string $label) => ['name' => $name, 'label' => $label], array_keys($t['variables']), $t['variables']),
            'body' => $custom->get($key)?->body ?? $t['body'],
            'default_body' => $t['body'],
            'customized' => $custom->has($key),
            'updated_by_name' => $custom->get($key)?->updated_by_name,
        ])->values()]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        abort_unless(Templates::exists($key), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']], attributes: ['body' => 'نص الرسالة']);

        $template = MessageTemplate::query()->firstOrNew(['key' => $key], ['tenant_id' => $this->tenant->idOrFail()]);
        $template->fill(['body' => $data['body'], 'updated_by_name' => $request->user()?->getAttribute('name')])->save();
        $this->audit->record('messages.template_updated', 'عدّل قالب رسالة «'.Templates::all()[$key]['label'].'»', $template);

        return $this->templates();
    }

    public function reset(string $key): JsonResponse
    {
        abort_unless(Templates::exists($key), 404);
        MessageTemplate::query()->where('key', $key)->delete();

        return $this->templates();
    }

    public function log(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template' => ['required', Rule::in(array_keys(Templates::all()))],
            'subject_type' => ['nullable', Rule::in(self::SUBJECTS)],
            'subject_id' => ['nullable', 'uuid', 'required_with:subject_type'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $user = $request->user();

        MessageLog::create([
            'tenant_id' => $this->tenant->idOrFail(),
            ...$data,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);

        return response()->json(['data' => null], Response::HTTP_CREATED);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(self::SUBJECTS)],
            'subject_id' => ['required', 'uuid'],
        ]);
        $labels = array_map(fn (array $t) => $t['label'], Templates::all());

        return response()->json(['data' => MessageLog::query()
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'])
            ->orderByDesc('seq')
            ->limit(50)
            ->get()
            ->map(fn (MessageLog $l): array => [
                'template' => $l->template,
                'label' => $labels[$l->template] ?? $l->template,
                'user_name' => $l->user_name,
                'created_at' => $l->created_at->toIso8601String(),
            ])]);
    }
}
