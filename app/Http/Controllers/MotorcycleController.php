<?php

namespace App\Http\Controllers;

use App\Http\Requests\Motorcycle\StoreMotorcycleRequest;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Person;
use App\Services\People\MemberPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MotorcycleController extends Controller
{
    public function __construct(private readonly MemberPhotoService $photos) {}

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', [Motorcycle::class, $organization]);

        $motorcycles = Motorcycle::query()
            ->inOrganization($organization->id)
            ->with('person')
            ->when($request->string('q')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('plate', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $this->attachRiders($motorcycles->getCollection(), $organization);

        return Inertia::render('Motorcycles/Index', [
            'motorcycles' => $motorcycles,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function store(StoreMotorcycleRequest $request, Organization $organization): RedirectResponse
    {
        $person = Person::query()->findOrFail($request->validated('person_id'));

        abort_unless($person->belongsToOrganization($organization->id), 403);

        Motorcycle::query()->create($request->validated());

        return back()->with('success', 'Motocicleta registrada.');
    }

    /**
     * @param  Collection<int, Motorcycle>  $motorcycles
     */
    private function attachRiders(Collection $motorcycles, Organization $organization): void
    {
        $pilotIds = $motorcycles->pluck('person_id')->filter()->unique()->values();
        $copilots = $pilotIds->isEmpty()
            ? collect()
            : OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('vehicle_role', 'passenger')
                ->whereIn('metadata->pilot_person_id', $pilotIds->all())
                ->with('person')
                ->get()
                ->groupBy(fn (OrganizationMember $member) => $member->metadata['pilot_person_id'] ?? '');

        $motorcycles->each(function (Motorcycle $motorcycle) use ($organization, $copilots): void {
            $riders = [];
            $pilot = $motorcycle->person;

            if ($pilot) {
                $riders[] = $this->riderPayload($organization, $pilot, 'Piloto');

                foreach ($copilots->get($pilot->id, collect()) as $membership) {
                    if ($membership->person instanceof Person) {
                        $riders[] = $this->riderPayload($organization, $membership->person, 'Copiloto');
                    }
                }
            }

            $motorcycle->setAttribute('riders', $riders);
        });
    }

    /**
     * @return array{role: string, name: string, photo_url: string|null}
     */
    private function riderPayload(Organization $organization, Person $person, string $role): array
    {
        return [
            'role' => $role,
            'name' => $person->full_name,
            'photo_url' => $this->photos->portraitUrl($person, route('people.photo', [$organization, $person])),
        ];
    }
}
