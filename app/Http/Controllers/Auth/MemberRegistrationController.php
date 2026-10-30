<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterMemberRequest;
use App\Models\Organization;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberRegistrationController extends Controller
{
    public function create(GoogleAuthService $googleAuth): Response|RedirectResponse
    {
        $user = request()->user();

        if ($user?->isSuperAdmin()) {
            return redirect()->route('organizations.index');
        }

        if ($user?->person_id || $user?->organizationRoles()->exists()) {
            $organization = $user->currentOrganization
                ?? $user->organizationRoles()->with('organization')->first()?->organization;

            return $organization
                ? redirect()->route('dashboard', $organization)
                : redirect()->route('organizations.index');
        }

        $names = $googleAuth->splitName($user?->name);
        $clubSlug = session('google_club', 'amm-cartagena');

        return Inertia::render('Auth/Register', [
            'googleUser' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'avatar' => $user?->avatar,
            ],
            'defaults' => $names,
            'organizations' => Organization::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'city']),
            'selectedClub' => $clubSlug,
        ]);
    }

    public function store(RegisterMemberRequest $request, RegisterMemberAction $action): RedirectResponse
    {
        $organization = Organization::query()->findOrFail($request->validated('organization_id'));
        $action->execute($request->user(), $organization, $request->validated());

        return redirect()
            ->route('dashboard', $organization)
            ->with('success', 'Inscripción completada. Bienvenido al club.');
    }
}
