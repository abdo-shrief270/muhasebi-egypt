<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\RegisterTenantData;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Events\TenantRegistered;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Modules\ModuleManifest;
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
            $tenant = new Tenant(['name' => $data->shopName, 'phone' => $data->phone]);
            $tenant->setTypes($data->shopTypes);
            $tenant->save();
            $types = array_map(fn (ShopType $t) => $t->value, $tenant->types());

            return $this->currentTenant->runAs($tenant->id, function () use ($tenant, $data, $types): User {
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
                // The optional modules made for this kind of shop start on trial.
                $suggested = array_keys(array_filter($this->registry->all(), fn (ModuleManifest $m) => $m->isSuggestedFor($types)));
                $this->modules->startTrials($tenant->id, $suggested);

                $referrer = $data->referralCode !== null ? Tenant::query()->where('code', $data->referralCode)->value('id') : null;
                $this->events->record(new TenantRegistered($tenant->id, $types[0], $owner->id, $types, $referrer));
                $this->audit->record('shop.registered', "سجّل المحل «{$tenant->name}»", $tenant, tenantId: $tenant->id);

                return $owner;
            });
        });
    }
}
