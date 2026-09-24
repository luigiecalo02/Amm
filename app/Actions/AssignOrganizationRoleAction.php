<?php

namespace App\Actions;

use App\Events\UserAssignedToOrganization;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;

class AssignOrganizationRoleAction
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function execute(Organization $organization, User $user, Role $role, ?User $actor = null): void
    {
        $this->permissions->assignRole($organization, $user, $role);

        if (! $user->current_organization_id) {
            $user->forceFill(['current_organization_id' => $organization->id])->save();
        }

        UserAssignedToOrganization::dispatch($user, $organization, $actor);
    }
}
