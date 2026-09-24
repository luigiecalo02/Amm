<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('roles.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('roles.create', $organization);
    }

    public function update(User $user, Role $role, Organization $organization): bool
    {
        return $role->organization_id === $organization->id
            && $user->hasPermission('roles.update', $organization);
    }

    public function delete(User $user, Role $role, Organization $organization): bool
    {
        return $role->organization_id === $organization->id
            && ! $role->is_system
            && $user->hasPermission('roles.delete', $organization);
    }
}
