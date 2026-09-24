<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $organizations = $user->isPlatformAdmin()
            ? Organization::query()->orderBy('name')->get()
            : Organization::query()
                ->whereIn('id', $user->organizationRoles()->select('organization_id'))
                ->orderBy('name')
                ->get();

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = $this->organizations->create($request->validated(), $request->user());

        return redirect()
            ->route('dashboard', $organization)
            ->with('success', 'Club creado correctamente.');
    }

    public function edit(Organization $organization): Response
    {
        $this->authorize('update', $organization);

        return Inertia::render('Organizations/Settings', [
            'club' => $organization,
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);
        $this->organizations->update($organization, $request->validated());

        return back()->with('success', 'Datos del club actualizados.');
    }
}
