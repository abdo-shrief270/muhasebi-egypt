<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Signed-in devices = the user's Sanctum tokens. Tokens are checked against the database on
 * every request, so deleting one signs that device out immediately.
 */
final class DeviceSessions
{
    public function __construct(
        private readonly Request $request,
        private readonly Auditor $audit,
    ) {}

    /** Signs a device in: a new token that remembers where it was issued. */
    public function issue(User $user, string $deviceName): string
    {
        $token = $user->createToken(Str::limit($deviceName, 120, ''));
        $token->accessToken->forceFill([
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ])->save();

        return $token->plainTextToken;
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function of(User $user): Collection
    {
        /** @var Collection<int, PersonalAccessToken> */
        return $user->tokens()->orderByRaw('last_used_at desc nulls last')->orderByDesc('id')->get();
    }

    /** The id of the token this request came with (null for first-party / test sessions). */
    public function currentId(User $user): ?int
    {
        $token = $user->currentAccessToken();
        $id = $token instanceof PersonalAccessToken ? $token->getKey() : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function revoke(User $user, int $tokenId, bool $byOwner = false): void
    {
        DB::transaction(function () use ($user, $tokenId, $byOwner): void {
            /** @var PersonalAccessToken|null $token */
            $token = $user->tokens()->whereKey($tokenId)->first();
            if ($token === null) {
                throw new DomainRuleException('الجهاز ده مش موجود أو خرج بالفعل.', 'session_not_found', 404);
            }

            $token->delete();
            $this->audit->record(
                $byOwner ? 'users.session_revoked' : 'auth.session_revoked',
                $byOwner ? "خرّج «{$user->name}» من جهاز «{$token->name}»" : "خرج من جهاز «{$token->name}»",
                $user,
                ['device' => $token->name],
            );
        });
    }

    /** Signs out every device except $keepId (null = all of them). Returns how many. */
    public function revokeAll(User $user, ?int $keepId, bool $byOwner = false): int
    {
        return DB::transaction(function () use ($user, $keepId, $byOwner): int {
            $count = $user->tokens()->when($keepId !== null, fn ($q) => $q->whereKeyNot($keepId))->delete();

            $this->audit->record(
                $byOwner ? 'users.sessions_revoked' : 'auth.sessions_revoked',
                $byOwner ? "خرّج «{$user->name}» من كل الأجهزة ({$count})" : "خرج من كل الأجهزة التانية ({$count})",
                $user,
                ['count' => $count],
            );

            return $count;
        });
    }
}
