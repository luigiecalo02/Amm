<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $request->route('organization');

        if (! $user || ! $organization instanceof Organization) {
            abort(403);
        }

        if (! $user->belongsToOrganization($organization)) {
            abort(403, 'No perteneces a esta organización.');
        }

        if ($user->current_organization_id !== $organization->id) {
            $user->forceFill(['current_organization_id' => $organization->id])->save();
        }

        return $next($request);
    }
}
