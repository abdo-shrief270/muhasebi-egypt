<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Console\AdminTwoFactorCommand;
use App\Modules\Billing\Console\CreateAdminCommand;
use App\Modules\Billing\Console\SendRenewalRemindersCommand;
use App\Modules\Billing\Console\SyncSubscriptionModulesCommand;
use App\Modules\Billing\Contracts\SubscriptionStanding;
use App\Modules\Billing\Listeners\AttributeAffiliateSignup;
use App\Modules\Billing\Listeners\WelcomeReferredShop;
use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Tenancy\TenantRequestGuard;
use Illuminate\Support\Facades\Gate;

final class BillingServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        TenantRegistered::class => [WelcomeReferredShop::class, AttributeAffiliateSignup::class],
    ];

    public function register(): void
    {
        $this->app->tag([SubscriptionGuard::class], TenantRequestGuard::TAG);
        $this->app->bind(SubscriptionStanding::class, SubscriptionStandingService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The platform's own admins; a shop user never passes it (and admins never pass shop gates).
        // Only with an admin token (tokenCan('admin')), never a shop session.
        // A partner (برنامج الشركاء) with their own token: only their own pages.
        Gate::define('affiliate', fn (mixed $user): bool => $user instanceof Affiliate && $user->tokenCan('affiliate'));
        Gate::define('platform-admin', fn (mixed $user): bool => $user instanceof PlatformAdmin && $user->is_active && $user->tokenCan('admin'));

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class, AdminTwoFactorCommand::class, SyncSubscriptionModulesCommand::class, SendRenewalRemindersCommand::class]);
        }
    }
}
