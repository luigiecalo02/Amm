<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function __construct(private readonly OrganizationContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (is_string($organization)) {
            $organization = Organization::query()->where('slug', $organization)->firstOrFail();
            $request->route()?->setParameter('organization', $organization);
        }

        if ($organization instanceof Organization) {
            $this->context->set($organization);
        }

        return $next($request);
    }
}
