<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\Inscriptions\InscriptionFormMapper;
use App\Services\People\MemberPhotoService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly InscriptionFormMapper $mapper,
        private readonly MemberPhotoService $photos,
    ) {}

    public function show(Organization $organization): Response
    {
        $person = $this->ownerIn($organization);
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        if ($person === null) {
            return Inertia::render('Profile/Show', [
                'linked' => false,
                'name' => $user->name,
                'photo_url' => null,
                'person' => null,
                'form' => null,
                'pilots' => [],
                'memberActive' => false,
                'canUpdateProfile' => false,
                'canUpdateSummary' => false,
                'canUpdateMotorcycle' => false,
            ]);
        }

        $person->load([
            'healthProfile',
            'emergencyContacts',
            'motorcycles.documents.type',
            'documents.type',
            'memberships' => fn ($query) => $query->where('organization_id', $organization->id),
        ]);

        $this->photos->present($person, $organization);
        $person->setAttribute(
            'photo_url',
            $this->photos->portraitUrl($person, route('profile.photo', $organization)),
        );

        $canUpdateSummary = $user->hasPermission('people.summary.update', $organization);

        return Inertia::render('Profile/Show', [
            'linked' => true,
            'name' => $person->full_name,
            'photo_url' => $person->getAttribute('photo_url'),
            'person' => $person,
            'form' => $this->mapper->fromPerson($person),
            'pilots' => $this->photos->pilotsPayload($person),
            'pilotChoices' => $canUpdateSummary ? $this->photos->pilotChoices($organization, $person) : [],
            'memberActive' => $person->isActiveIn($organization->id),
            'canUpdateProfile' => $user->can('update', [$person, $organization]),
            'canUpdateSummary' => $canUpdateSummary,
            'canUpdateMotorcycle' => $user->hasPermission('motorcycles.update', $organization)
                && $person->motorcycles->contains(fn ($motorcycle) => $motorcycle->person_id === $person->id),
        ]);
    }

    public function photo(Organization $organization): StreamedResponse
    {
        $person = $this->ownerIn($organization);
        abort_unless($person, 404);

        return $this->photos->response($person->photo, "photos/people/{$person->id}");
    }

    private function ownerIn(Organization $organization): ?Person
    {
        $person = request()->user()?->person;

        if (! $person || ! $person->belongsToOrganization($organization->id)) {
            return null;
        }

        return $person;
    }
}
