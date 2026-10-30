<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->organizationRoles()->exists();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->canAccessOrganization($organization) && $user->hasPermission('organization.view', $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->canAccessOrganization($organization) && $user->hasPermission('organization.update', $organization);
    }
}
