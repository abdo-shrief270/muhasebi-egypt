<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Models\PlatformAdmin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

#[Signature('billing:admin {email} {name} {--password= : set it without a prompt (scripts)}')]
#[Description('Create a platform admin (super admin), or reset their password')]
final class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: password('Password', required: true, validate: fn (string $v) => strlen($v) < 10 ? 'At least 10 characters.' : null));
        $admin = PlatformAdmin::query()->updateOrCreate(
            ['email' => mb_strtolower((string) $this->argument('email'))],
            ['name' => (string) $this->argument('name'), 'password' => $password, 'is_active' => true],
        );
        $this->info("Admin {$admin->email} is ready.");
        if (! $admin->hasTwoFactor()) {
            $this->warn("Signing in needs an authenticator app: php artisan billing:admin-2fa {$admin->email}");
        }

        return self::SUCCESS;
    }
}
