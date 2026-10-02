<?php

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

// A user's own channel: answers to their approval requests.
Broadcast::channel('users.{id}', fn (User $user, string $id): bool => $user->id === $id && $user->is_active);

// The owner app's live screens (owner, managers).
Broadcast::channel('tenants.{tenantId}.owner', fn (User $user, string $tenantId): bool => $user->tenant_id === $tenantId && $user->can('owner_app.alerts'));

// Approval requests waiting for whoever may approve them.
Broadcast::channel('tenants.{tenantId}.approvals', fn (User $user, string $tenantId): bool => $user->tenant_id === $tenantId && $user->can('owner_app.approve'));
