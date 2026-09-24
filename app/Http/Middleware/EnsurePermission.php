<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $organization = $request->route('organization');

        if (! $user || ! $organization instanceof Organization || ! $user->hasPermission($permission, $organization)) {
            abort(403, 'No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}
