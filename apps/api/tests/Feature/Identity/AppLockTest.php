<?php

namespace Tests\Feature\Identity;

use App\Support\Security\WebAuthn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use OpenSSLAsymmetricKey;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «قفل التطبيق»: a passkey (fingerprint / face) or the PIN unlocks a signed-in device. */
class AppLockTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private const ORIGIN = 'https://app.muhasebi.test';

    private OpenSSLAsymmetricKey $key;

    private string $credentialId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.webauthn.rp_id' => 'app.muhasebi.test', 'services.webauthn.origins' => [self::ORIGIN]]);
        $this->openShopWithStock();
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = WebAuthn::b64(random_bytes(16));
    }

    /** What the phone's authenticator would send back. */
    private function authData(int $count, bool $attested = false, bool $verified = true, string $rpId = 'app.muhasebi.test'): string
    {
        $flags = 0x01 | ($verified ? 0x04 : 0) | ($attested ? 0x40 : 0);
        $data = hash('sha256', $rpId, true).chr($flags).pack('N', $count);
        if ($attested) {
            $id = WebAuthn::unb64($this->credentialId);
            $data .= str_repeat("\0", 16).pack('n', strlen($id)).$id.'(cose key)';
        }

        return $data;
    }

    private function clientData(string $type, string $challenge, string $origin = self::ORIGIN): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $origin, 'crossOrigin' => false]);
    }

    private function register(): array
    {
        $options = $this->postJson('/api/v1/account/passkeys/options', ['password' => 'password'])->assertOk()->json('data');
        $this->assertSame('app.muhasebi.test', $options['rp']['id']);
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', openssl_pkey_get_details($this->key)['key']));

        return $this->postJson('/api/v1/account/passkeys', [
            'name' => 'موبايلي',
            'id' => $this->credentialId,
            'client_data_json' => WebAuthn::b64($this->clientData('webauthn.create', $options['challenge'])),
            'authenticator_data' => WebAuthn::b64($this->authData(0, attested: true)),
            'public_key' => WebAuthn::b64($der),
            'alg' => -7,
        ])->assertCreated()->json('data');
    }

    /** Fingerprint → a signed answer to the server's challenge. */
    private function signed(int $count, ?string $challenge = null, string $origin = self::ORIGIN): array
    {
        $challenge ??= $this->postJson('/api/v1/account/unlock/options')->assertOk()->json('data.challenge');
        $client = $this->clientData('webauthn.get', $challenge, $origin);
        $auth = $this->authData($count);
        openssl_sign($auth.hash('sha256', $client, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return ['passkey' => [
            'id' => $this->credentialId,
            'client_data_json' => WebAuthn::b64($client),
            'authenticator_data' => WebAuthn::b64($auth),
            'signature' => WebAuthn::b64($signature),
        ]];
    }

    public function test_a_passkey_is_registered_and_unlocks_the_device(): void
    {
        $this->postJson('/api/v1/account/passkeys/options', ['password' => 'wrong'])->assertUnprocessable()->assertJsonPath('code', 'password_incorrect');
        $passkey = $this->register();
        $this->assertSame('موبايلي', $passkey['name']);
        $this->assertSame([$this->credentialId], array_column($this->getJson('/api/v1/account/passkeys')->assertOk()->json('data'), 'credential_id'));

        $options = $this->postJson('/api/v1/account/unlock/options')->assertOk()->json('data');
        $this->assertSame([['type' => 'public-key', 'id' => $this->credentialId]], $options['allowCredentials']);
        $this->postJson('/api/v1/account/unlock', $this->signed(1, $options['challenge']))->assertOk()->assertJsonStructure(['data' => ['verified_until']]);

        // A replayed answer (same challenge, used once) or a counter going backwards is refused.
        $this->postJson('/api/v1/account/unlock', $this->signed(2, $options['challenge']))->assertUnprocessable()->assertJsonPath('code', 'passkey_incorrect');
        $this->postJson('/api/v1/account/unlock', $this->signed(1))->assertUnprocessable()->assertJsonPath('code', 'passkey_incorrect');
        // Another site can't use it.
        $this->postJson('/api/v1/account/unlock', $this->signed(5, origin: 'https://evil.test'))->assertUnprocessable();
        // A signature by another key.
        $real = $this->key;
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->postJson('/api/v1/account/unlock', $this->signed(6))->assertUnprocessable();
        $this->key = $real;
        $this->postJson('/api/v1/account/unlock', $this->signed(7))->assertOk();

        $this->deleteJson("/api/v1/account/passkeys/{$passkey['id']}")->assertNoContent();
        $this->assertSame([], $this->getJson('/api/v1/account/passkeys')->json('data'));
    }

    public function test_registration_needs_a_verified_user_and_the_right_site(): void
    {
        $options = $this->postJson('/api/v1/account/passkeys/options', ['password' => 'password'])->json('data');
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', openssl_pkey_get_details($this->key)['key']));
        $body = fn (string $auth, string $origin = self::ORIGIN) => [
            'id' => $this->credentialId,
            'client_data_json' => WebAuthn::b64($this->clientData('webauthn.create', $options['challenge'], $origin)),
            'authenticator_data' => WebAuthn::b64($auth),
            'public_key' => WebAuthn::b64($der),
            'alg' => -7,
        ];
        // No fingerprint / face (user not verified), or for another site: refused (the challenge is spent too).
        $this->postJson('/api/v1/account/passkeys', $body($this->authData(0, attested: true, verified: false)))->assertUnprocessable()->assertJsonPath('code', 'passkey_invalid');
        $options = $this->postJson('/api/v1/account/passkeys/options', ['password' => 'password'])->json('data');
        $this->postJson('/api/v1/account/passkeys', $body($this->authData(0, attested: true, rpId: 'evil.test')))->assertUnprocessable();
    }

    public function test_the_pin_unlocks_and_too_many_wrong_tries_sign_the_device_out(): void
    {
        $this->putJson('/api/v1/account/pin', ['password' => 'password', 'pin' => '4826'])->assertOk();
        $token = $this->owner->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/account/unlock', ['pin' => '4826'])->assertOk();
        foreach (range(1, 4) as $_) {
            $this->withToken($token)->postJson('/api/v1/account/unlock', ['pin' => '9999'])->assertUnprocessable()->assertJsonPath('code', 'pin_incorrect');
            $this->app['auth']->forgetGuards();
        }
        $this->withToken($token)->postJson('/api/v1/account/unlock', ['pin' => '9999'])->assertUnauthorized()->assertJsonPath('code', 'unlock_locked_out');
        $this->assertNull(PersonalAccessToken::findToken($token));
    }
}
