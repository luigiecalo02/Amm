<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;

class OrganizationMemberPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('members.view', $organization);
    }

    public function view(User $user, OrganizationMember $member, Organization $organization): bool
    {
        return $member->organization_id === $organization->id
            && $user->hasPermission('members.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('members.create', $organization);
    }

    public function update(User $user, OrganizationMember $member, Organization $organization): bool
    {
        return $member->organization_id === $organization->id
            && $user->hasPermission('members.update', $organization);
    }

    public function delete(User $user, OrganizationMember $member, Organization $organization): bool
    {
        return $member->organization_id === $organization->id
            && $user->hasPermission('members.delete', $organization);
    }
}
