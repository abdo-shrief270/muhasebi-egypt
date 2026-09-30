<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Http\Controllers;

use App\Modules\Feedback\Contracts\FeedbackInbox;
use App\Modules\Feedback\Models\Feedback;
use App\Modules\Feedback\Support\Scrub;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * «ابعت ملاحظة»: any signed-in user tells the platform team about a problem / idea / question.
 * The web attaches the page, the build and the screen size; the browser is read from the request.
 */
final class FeedbackController
{
    /** Per shop per day, on top of the per-user route throttle. */
    public const DAILY_LIMIT = 30;

    public function store(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FeedbackInbox::TYPES))],
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'page' => ['nullable', 'string', 'max:500'],
            'app_version' => ['nullable', 'string', 'max:60'],
            'screen' => ['nullable', 'string', 'regex:/^\d{2,5}x\d{2,5}$/'],
            'screenshot' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], attributes: ['type' => 'النوع', 'message' => 'الملاحظة', 'screenshot' => 'الصورة']);

        $tenantId = $tenant->idOrFail();
        if (Feedback::query()->where('created_at', '>=', now()->subDay())->count() >= self::DAILY_LIMIT) {
            throw new DomainRuleException('وصلنا ملاحظات كتير من المحل النهارده. ابعت تاني بكرة أو كلمنا واتساب.', 'feedback_limit', 429);
        }
        $user = $request->user();

        Feedback::create([
            'tenant_id' => $tenantId,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'type' => $data['type'],
            'message' => trim($data['message']),
            'page' => Scrub::path($data['page'] ?? null),
            'app_version' => $data['app_version'] ?? null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            'screen' => $data['screen'] ?? null,
            'screenshot_path' => $request->file('screenshot')?->store("feedback/{$tenantId}", 'local') ?: null,
        ]);

        return response()->json(['data' => ['received' => true]], Response::HTTP_CREATED);
    }
}
