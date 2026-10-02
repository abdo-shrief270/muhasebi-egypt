<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Support;

use App\Modules\OwnerApp\Contracts\ApprovalKind;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * What the app sends to ask for an approval: signed by the server when it refused the action, so
 * the summary the approver reads is the server's, and the approval can only match that action.
 */
final class ApprovalToken
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hash(ApprovalKind $kind, array $payload): string
    {
        return hash('sha256', $kind->value.'|'.json_encode(self::sorted($payload), JSON_UNESCAPED_UNICODE));
    }

    public static function issue(ApprovalKind $kind, string $hash, string $summary, int $amount, ?string $branchId, string $userId): string
    {
        return Crypt::encryptString(json_encode([
            'kind' => $kind->value,
            'hash' => $hash,
            'summary' => $summary,
            'amount' => $amount,
            'branch' => $branchId,
            'user' => $userId,
            'exp' => now()->addMinutes(30)->getTimestamp(),
        ], JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array{kind: string, hash: string, summary: string, amount: int, branch: string|null, user: string, exp: int}|null
     */
    public static function read(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($data) && ($data['exp'] ?? 0) > now()->getTimestamp() && ApprovalKind::tryFrom((string) ($data['kind'] ?? '')) !== null ? $data : null;
    }

    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sorted(...), $value);
    }
}
