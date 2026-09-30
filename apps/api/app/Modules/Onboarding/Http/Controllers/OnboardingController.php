<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Http\Controllers;

use App\Modules\Onboarding\Models\OnboardingPreference;
use App\Modules\Onboarding\Support\SetupChecklist;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «ابدأ من هنا» on the home page: the owner's (and managers') first steps, ticked from the shop's
 * data. Each user sees only the steps they may do; folding / hiding the card is remembered per user.
 */
final class OnboardingController
{
    public function __construct(
        private readonly SetupChecklist $checklist,
        private readonly CurrentTenant $tenant,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'collapsed' => ['sometimes', 'boolean'],
            'dismissed' => ['sometimes', 'boolean'],
        ]);
        $preference = $this->preference($request);
        if (array_key_exists('collapsed', $data)) {
            $preference->collapsed = (bool) $data['collapsed'];
        }
        if (array_key_exists('dismissed', $data)) {
            $preference->dismissed_at = $data['dismissed'] ? now() : null;
        }
        $preference->save();

        return response()->json(['data' => $this->state($request)]);
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable, 401);

        // The card is for whoever sets the shop up: the owner, and managers who add staff.
        $forUser = $user->can('owner') || $user->can('users.manage');
        $steps = $forUser ? $this->checklist->forUser($user, $this->tenant->idOrFail()) : [];
        $done = count(array_filter($steps, fn (array $s): bool => $s['done']));
        $preference = OnboardingPreference::query()->find((string) $request->user()?->getAuthIdentifier());

        return [
            'visible' => $steps !== [] && $done < count($steps) && $preference?->dismissed_at === null,
            'done' => $done,
            'total' => count($steps),
            'complete' => $steps !== [] && $done === count($steps),
            'collapsed' => (bool) $preference?->collapsed,
            'dismissed' => $preference?->dismissed_at !== null,
            'steps' => $steps,
        ];
    }

    private function preference(Request $request): OnboardingPreference
    {
        $userId = (string) $request->user()?->getAuthIdentifier();

        return OnboardingPreference::query()->find($userId) ?? new OnboardingPreference(['user_id' => $userId]);
    }
}
