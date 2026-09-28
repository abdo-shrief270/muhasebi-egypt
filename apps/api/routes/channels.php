<?php

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', fn (User $user, string $id): bool => $user->id === $id);

Broadcast::channel('tenants.{tenantId}.owner', fn (User $user, string $tenantId): bool => $user->tenant_id === $tenantId && $user->is_owner);
