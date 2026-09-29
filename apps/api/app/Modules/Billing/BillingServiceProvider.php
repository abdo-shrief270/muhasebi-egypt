<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Console\CreateAdminCommand;
use App\Modules\Billing\Console\SyncSubscriptionModulesCommand;
use App\Modules\Billing\Models\PlatformAdmin;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Tenancy\TenantRequestGuard;
use Illuminate\Support\Facades\Gate;

final class BillingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->tag([SubscriptionGuard::class], TenantRequestGuard::TAG);
    }

    public function boot(): void
    {
        parent::boot();

        // The platform's own admins; a shop user never passes it (and admins never pass shop gates).
        Gate::define('platform-admin', fn (mixed $user): bool => $user instanceof PlatformAdmin && $user->is_active);

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class, SyncSubscriptionModulesCommand::class]);
        }
    }
}
