<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateBirthdayAlertSettingsRequest;
use App\Http\Requests\Organization\UpdateClubNoticeSettingsRequest;
use App\Http\Requests\Organization\UpdateDocumentAlertSettingsRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use App\Support\AlertSchedule;
use App\Support\BirthdayAlertRules;
use App\Support\DocumentAlertRules;
use App\Support\UserAlertPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly DocumentAlertRules $alerts,
        private readonly BirthdayAlertRules $birthdays,
        private readonly AlertSchedule $schedule,
        private readonly UserAlertPreferences $userAlerts,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $organizations = $user->accessibleOrganizations()
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            'canCreate' => $user->can('create', Organization::class),
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
        $this->authorize('view', $organization);
        $requestUser = request()->user();
        abort_unless($requestUser instanceof User, 403);

        return Inertia::render('Organizations/Settings', [
            'club' => $organization,
            'canUpdate' => request()->user()?->can('update', $organization) ?? false,
            'documentAlerts' => $this->alerts->forOrganization($organization),
            'documentAlertInterval' => $this->alerts->repeatEvery($organization),
            'birthdayAlertDays' => $this->birthdays->daysBefore($organization),
            'alertTime' => $this->schedule->timeFor($organization),
            'alertPreferences' => $this->userAlerts->forUser($requestUser, $organization),
        ]);
    }

    public function updateNotices(UpdateClubNoticeSettingsRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);
        $this->alerts->save($organization, $request->validated());
        $organization->refresh();
        $this->birthdays->save($organization, (int) $request->validated('birthday_days'));
        $this->schedule->save($organization, $request->validated('alert_time'));

        return back()->with('success', 'Avisos del club actualizados.');
    }

    public function updateBirthdayAlerts(UpdateBirthdayAlertSettingsRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);
        $this->birthdays->save($organization, (int) $request->validated('days'));
        $this->schedule->save($organization, $request->validated('alert_time'));

        return back()->with('success', 'Plazo de cumpleaños actualizado.');
    }

    public function updateAlerts(UpdateDocumentAlertSettingsRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);
        $this->alerts->save($organization, $request->validated());
        $this->schedule->save($organization, $request->validated('alert_time'));

        return back()->with('success', 'Plazos de aviso actualizados.');
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);
        $this->organizations->update($organization, $request->validated());

        return back()->with('success', 'Datos del club actualizados.');
    }
}
