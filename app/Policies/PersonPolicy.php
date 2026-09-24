<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('people.view', $organization);
    }

    public function view(User $user, Person $person, Organization $organization): bool
    {
        return $person->belongsToOrganization($organization->id)
            && $user->hasPermission('people.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('people.create', $organization);
    }

    public function update(User $user, Person $person, Organization $organization): bool
    {
        return $person->belongsToOrganization($organization->id)
            && $user->hasPermission('people.update', $organization);
    }

    public function delete(User $user, Person $person, Organization $organization): bool
    {
        return $person->belongsToOrganization($organization->id)
            && $user->hasPermission('people.delete', $organization);
    }
}
