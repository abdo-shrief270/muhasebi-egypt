<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\Categories;
use App\Modules\Notifications\Support\PushSender;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** The signed-in user's own push devices and what they want pushed (own-account routes). */
final class PushController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function key(PushSender $sender): JsonResponse
    {
        return response()->json(['data' => [
            'configured' => $sender->configured(),
            'public_key' => $sender->configured() ? (string) config('services.webpush.public_key') : null,
        ]]);
    }

    /** This browser / installed app gets the user's notifications (a device moves to whoever signs in on it). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'device' => ['nullable', 'string', 'max:120'],
        ]);

        PushSubscription::withoutTenancy()->where('endpoint', $data['endpoint'])->delete();
        PushSubscription::create([
            'tenant_id' => $this->tenant->idOrFail(),
            'user_id' => $request->user()->getAuthIdentifier(),
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'device' => $data['device'] ?? null,
        ]);

        return response()->json(['data' => ['subscribed' => true]], 201);
    }

    public function unsubscribe(Request $request): Response
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::query()->where('user_id', $request->user()->getAuthIdentifier())->where('endpoint', $data['endpoint'])->delete();

        return response()->noContent();
    }

    /** A test notification to this user's own devices. */
    public function test(Request $request, PushSender $sender): JsonResponse
    {
        $devices = PushSubscription::query()->where('user_id', $request->user()->getAuthIdentifier())->get();
        $gone = $sender->send($devices->map(fn (PushSubscription $d) => ['endpoint' => $d->endpoint, 'p256dh' => $d->p256dh, 'auth' => $d->auth])->all(), [
            'title' => 'الإشعارات شغالة ✅',
            'body' => 'كده هيوصلك المهم على الجهاز ده.',
            'url' => '/settings/notifications',
            'tag' => 'test',
        ]);
        if ($gone !== []) {
            PushSubscription::query()->whereIn('endpoint', $gone)->delete();
        }

        return response()->json(['data' => ['devices' => $devices->count() - count($gone)]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'muted' => ['present', 'array'],
            'muted.*' => ['string', Rule::in(array_keys(Categories::ALL))],
            'quiet_from' => ['nullable', 'date_format:H:i', 'required_with:quiet_to'],
            'quiet_to' => ['nullable', 'date_format:H:i', 'required_with:quiet_from'],
        ]);

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->getAuthIdentifier()],
            [
                'tenant_id' => $this->tenant->idOrFail(),
                'muted' => array_values(array_unique($data['muted'])),
                'quiet_from' => $data['quiet_from'] ?? null,
                'quiet_to' => $data['quiet_to'] ?? null,
            ],
        );

        return response()->json(['data' => $this->present($request)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Request $request): array
    {
        $user = $request->user();
        $pref = NotificationPreference::query()->find($user->getAuthIdentifier());
        $muted = $pref->muted ?? [];

        $categories = [];
        foreach (Categories::ALL as $key => $category) {
            if ($user->can($category['permission'])) {
                $categories[] = ['key' => $key, 'label' => $category['label'], 'description' => $category['description'], 'muted' => in_array($key, $muted, true)];
            }
        }

        return [
            'categories' => $categories,
            'quiet_from' => $pref?->quiet_from ? substr($pref->quiet_from, 0, 5) : null,
            'quiet_to' => $pref?->quiet_to ? substr($pref->quiet_to, 0, 5) : null,
            'devices' => PushSubscription::query()->where('user_id', $user->getAuthIdentifier())->count(),
        ];
    }
}
