<?php

namespace App\Http\Controllers;

use App\Http\Requests\Motorcycle\StoreMotorcycleRequest;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MotorcycleController extends Controller
{
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

        return Inertia::render('Motorcycles/Index', [
            'motorcycles' => $motorcycles,
            'people' => Person::query()->inOrganization($organization->id)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'document_number']),
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
}
