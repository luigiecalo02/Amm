<?php

namespace App\Http\Controllers;

use App\Actions\CreatePersonAction;
use App\Actions\UpdateMemberPhotosAction;
use App\Actions\UpdatePersonAction;
use App\Http\Requests\Person\StorePersonRequest;
use App\Http\Requests\Person\UpdateMemberPhotosRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use App\Http\Requests\Person\UpdatePersonSummaryRequest;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Inscriptions\InscriptionFormMapper;
use App\Services\People\MemberPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonController extends Controller
{
    public function __construct(
        private readonly InscriptionFormMapper $mapper,
        private readonly MemberPhotoService $photos,
    ) {}

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

        $people->getCollection()->each(function (Person $person) use ($organization): void {
            $person->setAttribute(
                'photo_url',
                $this->photos->portraitUrl($person, route('people.photo', [$organization, $person])),
            );
        });

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
            'motorcycles.documents.type',
            'documents.type',
            'memberships' => fn ($query) => $query->where('organization_id', $organization->id),
        ]);

        $this->photos->present($person, $organization);

        $user = request()->user();
        $canUpdateSummary = $user?->hasPermission('people.summary.update', $organization) ?? false;

        return Inertia::render('People/Show', [
            'person' => $person,
            'form' => $this->mapper->fromPerson($person),
            'pilots' => $this->photos->pilotsPayload($person),
            'pilotChoices' => $canUpdateSummary ? $this->photos->pilotChoices($organization, $person) : [],
            'memberActive' => $person->isActiveIn($organization->id),
            'canUpdateProfile' => $user?->can('update', [$person, $organization]) ?? false,
            'canUpdateSummary' => $canUpdateSummary,
            'canUpdateMotorcycle' => ($user?->hasPermission('motorcycles.update', $organization) ?? false)
                && $person->motorcycles->contains(fn ($motorcycle) => $motorcycle->person_id === $person->id),
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

    public function updateSummary(
        UpdatePersonSummaryRequest $request,
        Organization $organization,
        Person $person,
        UpdatePersonAction $action,
    ): RedirectResponse {
        $data = Arr::except($request->validated(), [
            'motorcycle',
            'membership_number',
            'joined_at',
            'notes',
        ]);

        if (($data['vehicle_role'] ?? null) !== 'passenger') {
            unset($data['pilot_person_id']);
        }

        $action->execute($person, $organization, $data);

        return back()->with('success', 'Datos de la ficha actualizados.');
    }

    public function updatePhotos(
        UpdateMemberPhotosRequest $request,
        Organization $organization,
        Person $person,
        UpdateMemberPhotosAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $action->execute(
            $person,
            $organization,
            $actor,
            $request->validated(),
            $request->file('photo'),
            $request->file('motorcycle_photo'),
        );

        return back()->with('success', 'Fotos del integrante actualizadas.');
    }

    public function photo(Organization $organization, Person $person): StreamedResponse
    {
        $this->authorize('view', [$person, $organization]);

        return $this->photos->response($person->photo, "photos/people/{$person->id}");
    }

    public function motorcyclePhoto(Organization $organization, Person $person, Motorcycle $motorcycle): StreamedResponse
    {
        $this->authorize('view', [$person, $organization]);
        abort_unless($motorcycle->person_id === $person->id, 404);

        return $this->photos->response($motorcycle->photo, "photos/motorcycles/{$motorcycle->id}");
    }
}
