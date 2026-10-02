<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in user's approval PIN: typed on a cashier's screen to OK something past the
 * owner's limits (owner app). Setting or removing it needs the account password.
 */
final class PinController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => ['has_pin' => $request->user()->pin_hash !== null]]);
    }

    public function update(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ], ['pin.regex' => 'الـ PIN لازم يبقى من 4 لـ 6 أرقام.']);
        $user = $request->user();
        if (! Hash::check($data['password'], (string) $user->password)) {
            throw new DomainRuleException('كلمة السر مش صح.', 'password_incorrect');
        }
        if (in_array($data['pin'], ['0000', '1234', '1111', '123456', '000000', '111111'], true)) {
            throw new DomainRuleException('الـ PIN ده سهل يتخمّن. اختار أرقام تانية.', 'pin_weak');
        }

        $user->forceFill(['pin_hash' => Hash::make($data['pin'])])->save();
        $audit->record('account.pin_set', "{$user->name} غيّر الـ PIN بتاع الموافقات", $user);

        return response()->json(['data' => ['has_pin' => true]]);
    }

    public function destroy(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($data['password'], (string) $user->password)) {
            throw new DomainRuleException('كلمة السر مش صح.', 'password_incorrect');
        }
        $user->forceFill(['pin_hash' => null])->save();
        $audit->record('account.pin_removed', "{$user->name} شال الـ PIN بتاع الموافقات", $user);

        return response()->json(['data' => ['has_pin' => false]]);
    }
}
