<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Person;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Organization $organization): Response
    {
        $expiring = Document::query()
            ->inOrganization($organization->id)
            ->with(['type', 'person', 'motorcycle'])
            ->expiringSoon(30)
            ->orderBy('expiration_date')
            ->limit(8)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'members' => OrganizationMember::query()->forOrganization($organization->id)->where('status', 'active')->count(),
                'people' => Person::query()->inOrganization($organization->id)->count(),
                'motorcycles' => Motorcycle::query()->inOrganization($organization->id)->count(),
                'expiring' => Document::query()->inOrganization($organization->id)->expiringSoon(30)->count(),
            ],
            'expiringDocuments' => $expiring,
            'recentMembers' => OrganizationMember::query()
                ->forOrganization($organization->id)
                ->with('person')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }
}
