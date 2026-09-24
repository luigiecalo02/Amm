<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Events\MemberAdded;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', [OrganizationMember::class, $organization]);

        $members = OrganizationMember::query()
            ->forOrganization($organization->id)
            ->with('person')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest('joined_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => ['status' => $request->string('status')->toString()],
        ]);
    }

    public function store(StoreMemberRequest $request, Organization $organization): RedirectResponse
    {
        $person = Person::query()->findOrFail($request->validated('person_id'));

        $member = OrganizationMember::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'person_id' => $person->id,
            ],
            [
                'membership_number' => $request->validated('membership_number'),
                'joined_at' => $request->validated('joined_at') ?? now()->toDateString(),
                'status' => MembershipStatus::Active,
                'vehicle_role' => $request->validated('vehicle_role'),
                'notes' => $request->validated('notes'),
            ]
        );

        MemberAdded::dispatch($member, $organization, $request->user());

        return back()->with('success', 'Miembro vinculado al club.');
    }
}
