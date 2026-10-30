<?php

namespace App\Http\Controllers;

use App\Actions\CreatePersonAction;
use App\Actions\UpdatePersonAction;
use App\Http\Requests\Person\StorePersonRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', [Person::class, $organization]);

        $people = Person::query()
            ->inOrganization($organization->id)
            ->with(['memberships' => fn ($query) => $query->where('organization_id', $organization->id)])
            ->when($request->string('q')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('People/Index', [
            'people' => $people,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', [Person::class, $organization]);

        return Inertia::render('People/Create');
    }

    public function store(StorePersonRequest $request, Organization $organization, CreatePersonAction $action): RedirectResponse
    {
        $person = $action->execute($organization, $request->validated(), $request->user());

        return redirect()
            ->route('people.show', [$organization, $person])
            ->with('success', 'Persona inscrita en el club.');
    }

    public function show(Organization $organization, Person $person): Response
    {
        $this->authorize('view', [$person, $organization]);

        $person->load([
            'healthProfile',
            'emergencyContacts',
            'motorcycles',
            'documents.type',
            'memberships' => fn ($query) => $query->where('organization_id', $organization->id),
        ]);

        return Inertia::render('People/Show', [
            'person' => $person,
        ]);
    }

    public function edit(Organization $organization, Person $person): Response
    {
        $this->authorize('update', [$person, $organization]);

        $person->load(['healthProfile', 'emergencyContacts']);

        return Inertia::render('People/Edit', [
            'person' => $person,
        ]);
    }

    public function update(UpdatePersonRequest $request, Organization $organization, Person $person, UpdatePersonAction $action): RedirectResponse
    {
        $this->authorize('update', [$person, $organization]);

        $action->execute($person, $organization, $request->validated());

        return redirect()
            ->route('people.show', [$organization, $person])
            ->with('success', 'Datos actualizados.');
    }
}
