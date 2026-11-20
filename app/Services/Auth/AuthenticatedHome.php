<?php

namespace App\Services\Auth;

use App\Models\User;

final class AuthenticatedHome
{
    public function url(?User $user): string
    {
        if (! $user) {
            return route('login');
        }

        if ($user->isSuperAdmin()) {
            return route('organizations.index');
        }

        if (! $user->person_id && ! $user->organizationRoles()->exists()) {
            return route('register.member');
        }

        $organization = $user->currentOrganization
            ?? $user->organizationRoles()->with('organization')->first()?->organization;

        if ($organization && filled($organization->slug)) {
            return route('dashboard', $organization);
        }

        return route('organizations.index');
    }
}
