<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Support\Security\Totp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('billing:admin-2fa {email}')]
#[Description("Set up (or replace) a platform admin's authenticator app — required to sign in")]
final class AdminTwoFactorCommand extends Command
{
    public function handle(): int
    {
        $admin = PlatformAdmin::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();
        if ($admin === null) {
            $this->error('No such admin.');

            return self::FAILURE;
        }

        $secret = Totp::generateSecret();
        $this->line('Add this to your authenticator app (Google Authenticator, Authy, 1Password…):');
        $this->newLine();
        $this->line("  Key:  {$secret}");
        $this->line('  URL:  '.Totp::provisioningUri($secret, $admin->email, 'Muhasebi Admin'));
        $this->newLine();

        $code = text('Enter the 6-digit code the app shows', required: true);
        if (Totp::verify($secret, $code) === null) {
            $this->error('Wrong code; nothing changed. Run the command again.');

            return self::FAILURE;
        }

        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => null])->save();
        $admin->tokens()->delete(); // sign out everywhere with the old setup
        $this->info("Two-factor sign-in is on for {$admin->email}.");

        return self::SUCCESS;
    }
}
