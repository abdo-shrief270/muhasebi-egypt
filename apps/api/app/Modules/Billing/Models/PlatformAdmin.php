<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Security\Totp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Someone who runs the platform (super admin): not tied to any shop.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret encrypted; set up with `php artisan billing:admin-2fa`
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_step
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'last_login_at', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step'])]
#[Hidden(['password', 'two_factor_secret'])]
final class PlatformAdmin extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Checks an authenticator code; a code is accepted once. */
    public function verifyCode(string $code): bool
    {
        if (! $this->hasTwoFactor()) {
            return false;
        }
        $step = Totp::verify((string) $this->two_factor_secret, $code, afterStep: $this->two_factor_last_step);
        if ($step === null) {
            return false;
        }
        $this->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }
}
