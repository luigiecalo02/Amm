<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('users.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('users.create', $organization);
    }

    public function update(User $user, User $model, Organization $organization): bool
    {
        return $model->belongsToOrganization($organization)
            && $user->hasPermission('users.update', $organization);
    }

    public function delete(User $user, User $model, Organization $organization): bool
    {
        return $model->belongsToOrganization($organization)
            && $user->hasPermission('users.delete', $organization);
    }
}
