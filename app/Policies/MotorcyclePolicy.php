<?php

namespace App\Policies;

use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\User;

class MotorcyclePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('motorcycles.view', $organization);
    }

    public function view(User $user, Motorcycle $motorcycle, Organization $organization): bool
    {
        return $motorcycle->belongsToOrganization($organization->id)
            && $user->hasPermission('motorcycles.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('motorcycles.create', $organization);
    }

    public function update(User $user, Motorcycle $motorcycle, Organization $organization): bool
    {
        return $motorcycle->belongsToOrganization($organization->id)
            && $user->hasPermission('motorcycles.update', $organization);
    }

    public function delete(User $user, Motorcycle $motorcycle, Organization $organization): bool
    {
        return $motorcycle->belongsToOrganization($organization->id)
            && $user->hasPermission('motorcycles.delete', $organization);
    }
}
