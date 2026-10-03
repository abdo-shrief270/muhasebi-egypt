<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Auth\AppUnlock;
use App\Modules\Identity\Contracts\AccountSecurity;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Contracts\PlatformShops;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Identity\Contracts\ShopProfile;
use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Identity\Models\User;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Security\WebAuthn;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

final class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShopDirectory::class, ShopDirectoryService::class);
        $this->app->bind(ShopProfile::class, ShopProfileService::class);
        $this->app->bind(PlatformShops::class, PlatformShopsService::class);
        $this->app->bind(BranchDirectory::class, BranchDirectoryService::class);
        $this->app->bind(StaffDirectory::class, StaffDirectoryService::class);
        $this->app->bind(AccountSecurity::class, AppUnlock::class);
        $this->app->singleton(WebAuthn::class, fn () => new WebAuthn(
            (string) config('services.webauthn.rp_id'),
            (array) config('services.webauthn.origins'),
        ));
        $this->app->scoped(PermissionResolver::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Every permission declared in a module manifest becomes a gate ability:
        // `can:sales.sell` middleware, $user->can('sales.sell'), @can in policies…
        Gate::before(function (mixed $user, string $ability): ?bool {
            if (! $user instanceof User || ! array_key_exists($ability, $this->app->make(ModuleRegistry::class)->permissions())) {
                return null;
            }

            return $this->app->make(PermissionResolver::class)->allows($user, $ability);
        });

        // Users aren't tenant scoped (they're looked up before a tenant is known), so bind them to the current shop explicitly.
        Route::bind('user', fn (string $id): User => User::query()
            ->where('tenant_id', $this->app->make(CurrentTenant::class)->idOrFail())
            ->findOrFail($id));

        Gate::define('owner', fn (User $user): bool => $user->is_owner && $user->is_active);
    }
}
