<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && ! $user->isSuperAdmin()
            && ! $user->person_id
            && ! $user->organizationRoles()->exists()
            && ! $request->routeIs('register.member', 'register.member.store', 'logout')
        ) {
            return redirect()->route('register.member');
        }

        return $next($request);
    }
}
