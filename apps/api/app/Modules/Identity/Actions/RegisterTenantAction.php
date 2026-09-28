<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\RegisterTenantData;
use App\Modules\Identity\Events\TenantRegistered;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Creates a shop with its main branch and owner, and starts trials for the modules
 * recommended for its shop type.
 */
final class RegisterTenantAction
{
    public function __construct(
        private readonly TenantModules $modules,
        private readonly EventRecorder $events,
        private readonly CurrentTenant $currentTenant,
        private readonly ModuleRegistry $registry,
        private readonly Auditor $audit,
    ) {}

    public function handle(RegisterTenantData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $tenant = Tenant::create([
                'name' => $data->shopName,
                'phone' => $data->phone,
                'shop_type' => $data->shopType,
            ]);

            return $this->currentTenant->runAs($tenant->id, function () use ($tenant, $data): User {
                Branch::create([
                    'name' => $data->branchName,
                    'phone' => $data->phone,
                    'invoice_prefix' => 'B1',
                    'is_main' => true,
                ]);

                $owner = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data->ownerName,
                    'phone' => $data->phone,
                    'email' => $data->email,
                    'password' => $data->password,
                    'is_owner' => true,
                ]);

                foreach (DefaultRoles::all(array_keys($this->registry->permissions())) as $key => $role) {
                    Role::create(['key' => $key, 'name' => $role['name'], 'permissions' => $role['permissions']]);
                }

                // Must succeed together with the registration, so it's a contract call, not an event.
                $this->modules->startTrials($tenant->id, $data->shopType->recommendedModules());

                $this->events->record(new TenantRegistered($tenant->id, $data->shopType->value, $owner->id));
                $this->audit->record('shop.registered', "سجّل المحل «{$tenant->name}»", $tenant, tenantId: $tenant->id);

                return $owner;
            });
        });
    }
}
