<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\Passkey;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Security\WebAuthn;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in user's passkeys: a fingerprint / face on a phone or laptop that unlocks the app
 * («قفل التطبيق») and confirms big approvals. Adding one needs the account password.
 */
final class PasskeyController
{
    public function __construct(
        private readonly WebAuthn $webauthn,
        private readonly Cache $cache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $keys = Passkey::query()->where('user_id', $request->user()->id)->latest()->get();

        return response()->json(['data' => $keys->map(fn (Passkey $k) => $k->toPublic())->all()]);
    }

    /** What navigator.credentials.create() needs (the browser makes the key after the fingerprint). */
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($data['password'], (string) $user->password)) {
            throw new DomainRuleException('كلمة السر مش صح.', 'password_incorrect');
        }
        $challenge = WebAuthn::challenge();
        $this->cache->put("passkey-register:{$user->id}", $challenge, now()->addMinutes(5));

        return response()->json(['data' => [
            'challenge' => $challenge,
            'rp' => ['id' => $this->webauthn->rpId(), 'name' => 'محاسبي'],
            'user' => ['id' => WebAuthn::b64($user->id), 'name' => (string) $user->phone, 'displayName' => $user->name],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => WebAuthn::ES256], ['type' => 'public-key', 'alg' => WebAuthn::RS256]],
            'authenticatorSelection' => ['userVerification' => 'required', 'residentKey' => 'preferred'],
            'excludeCredentials' => Passkey::query()->where('user_id', $user->id)->pluck('credential_id')
                ->map(fn (string $id) => ['type' => 'public-key', 'id' => $id])->all(),
            'attestation' => 'none',
            'timeout' => 60000,
        ]]);
    }

    public function store(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'id' => ['required', 'string', 'max:512', 'regex:/^[A-Za-z0-9_-]+$/'],
            'client_data_json' => ['required', 'string', 'max:4000'],
            'authenticator_data' => ['required', 'string', 'max:4000'],
            'public_key' => ['required', 'string', 'max:4000'],
            'alg' => ['required', 'integer'],
        ]);
        $user = $request->user();
        $challenge = (string) $this->cache->pull("passkey-register:{$user->id}", '');
        $key = $challenge === '' ? null : $this->webauthn->register(
            $challenge,
            $data['id'],
            WebAuthn::unb64($data['client_data_json']),
            WebAuthn::unb64($data['authenticator_data']),
            WebAuthn::unb64($data['public_key']),
            (int) $data['alg'],
        );
        if ($key === null) {
            throw new DomainRuleException('مقدرناش نسجّل البصمة دي. جرّب تاني.', 'passkey_invalid');
        }
        if (Passkey::query()->where('credential_id', $data['id'])->exists()) {
            throw new DomainRuleException('البصمة دي متسجلة قبل كده.', 'passkey_exists');
        }

        $passkey = Passkey::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'credential_id' => $data['id'],
            'public_key' => $key['public_key'],
            'alg' => (int) $data['alg'],
            'sign_count' => $key['sign_count'],
            'name' => trim((string) ($data['name'] ?? '')) ?: 'جهاز',
        ]);
        $audit->record('account.passkey_added', "{$user->name} سجّل بصمة على «{$passkey->name}»", $passkey);

        return response()->json(['data' => $passkey->toPublic()], 201);
    }

    public function destroy(Request $request, Passkey $passkey, Auditor $audit): Response
    {
        abort_unless($passkey->user_id === $request->user()->id, 404);
        $passkey->delete();
        $audit->record('account.passkey_removed', "{$request->user()->name} شال البصمة بتاعة «{$passkey->name}»", $passkey);

        return response()->noContent();
    }
}
