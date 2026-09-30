<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Auth\DeviceSessions;
use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Anyone changes their own password (needs the current one); other devices are signed out. */
final class PasswordController
{
    public function update(Request $request, DeviceSessions $sessions, Auditor $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], attributes: ['current_password' => 'كلمة السر الحالية', 'password' => 'كلمة السر الجديدة']);

        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            throw new DomainRuleException('كلمة السر الحالية غلط.', 'wrong_password', 422);
        }

        $signedOut = DB::transaction(function () use ($user, $data, $sessions, $audit): int {
            $user->forceFill(['password' => $data['password']])->save();
            $audit->record('account.password_changed', "غيّر كلمة السر بتاعته ({$user->name})", $user);

            return $sessions->revokeAll($user, $sessions->currentId($user));
        });

        return response()->json(['data' => ['signed_out_devices' => $signedOut]]);
    }
}
