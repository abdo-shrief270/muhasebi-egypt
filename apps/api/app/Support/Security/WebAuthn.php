<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Passkeys (WebAuthn), the parts we need: registering a device's key and checking that it signed
 * our challenge. The browser hands us the public key itself (`getPublicKey()`, SPKI DER), so no
 * CBOR is parsed; attestation is "none" (we trust the signed-in user registering their own
 * device). Keys are ES256 (-7) or RS256 (-257), verified with openssl.
 */
final class WebAuthn
{
    public const ES256 = -7;

    public const RS256 = -257;

    private const FLAG_USER_PRESENT = 0x01;

    private const FLAG_USER_VERIFIED = 0x04;

    private const FLAG_ATTESTED = 0x40;

    public function __construct(
        private readonly string $rpId,
        /** @var list<string> */
        private readonly array $origins,
    ) {}

    public function rpId(): string
    {
        return $this->rpId;
    }

    public static function challenge(): string
    {
        return self::b64(random_bytes(32));
    }

    /**
     * A new passkey: the browser's answer to navigator.credentials.create().
     *
     * @return array{public_key: string, sign_count: int}|null PEM key + counter, null when it doesn't check out
     */
    public function register(string $challenge, string $credentialId, string $clientDataJson, string $authenticatorData, string $publicKeyDer, int $alg): ?array
    {
        if (! in_array($alg, [self::ES256, self::RS256], true) || ! $this->clientData($clientDataJson, 'webauthn.create', $challenge)) {
            return null;
        }
        $auth = $this->authData($authenticatorData);
        if ($auth === null || ($auth['flags'] & self::FLAG_ATTESTED) === 0) {
            return null;
        }
        // Attested credential data: aaguid (16), id length (2), id.
        $rest = substr($authenticatorData, 37);
        if (strlen($rest) < 18) {
            return null;
        }
        $length = unpack('n', substr($rest, 16, 2))[1];
        if (! hash_equals(substr($rest, 18, $length), self::unb64($credentialId))) {
            return null;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($publicKeyDer), 64, "\n").'-----END PUBLIC KEY-----'."\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            return null;
        }
        $type = openssl_pkey_get_details($key)['type'] ?? null;
        if (($alg === self::ES256 && $type !== OPENSSL_KEYTYPE_EC) || ($alg === self::RS256 && $type !== OPENSSL_KEYTYPE_RSA)) {
            return null;
        }

        return ['public_key' => $pem, 'sign_count' => $auth['sign_count']];
    }

    /**
     * A signature with a registered passkey (navigator.credentials.get()).
     *
     * @return int|null the authenticator's new counter, null when it doesn't check out
     */
    public function verify(string $challenge, string $publicKeyPem, int $storedCount, string $clientDataJson, string $authenticatorData, string $signature): ?int
    {
        if (! $this->clientData($clientDataJson, 'webauthn.get', $challenge)) {
            return null;
        }
        $auth = $this->authData($authenticatorData);
        if ($auth === null) {
            return null;
        }
        // A counter that went backwards means a copied key (authenticators that don't count send 0).
        if ($auth['sign_count'] !== 0 && $auth['sign_count'] <= $storedCount) {
            return null;
        }
        $ok = openssl_verify($authenticatorData.hash('sha256', $clientDataJson, true), $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return $ok === 1 ? $auth['sign_count'] : null;
    }

    private function clientData(string $json, string $type, string $challenge): bool
    {
        $data = json_decode($json, true);

        return is_array($data)
            && ($data['type'] ?? null) === $type
            && is_string($data['challenge'] ?? null) && hash_equals($challenge, $data['challenge'])
            && in_array($data['origin'] ?? null, $this->origins, true);
    }

    /**
     * @return array{flags: int, sign_count: int}|null
     */
    private function authData(string $data): ?array
    {
        if (strlen($data) < 37 || ! hash_equals(hash('sha256', $this->rpId, true), substr($data, 0, 32))) {
            return null;
        }
        $flags = ord($data[32]);
        // A person was there and unlocked the key (fingerprint, face, device PIN).
        if (($flags & self::FLAG_USER_PRESENT) === 0 || ($flags & self::FLAG_USER_VERIFIED) === 0) {
            return null;
        }

        return ['flags' => $flags, 'sign_count' => unpack('N', substr($data, 33, 4))[1]];
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }
}
