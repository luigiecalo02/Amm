<?php

namespace App\Http\Controllers;

use App\Actions\AssignOrganizationRoleAction;
use App\Http\Requests\User\StoreOrganizationUserRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationUserController extends Controller
{
    public function index(Organization $organization): Response
    {
        $this->authorize('viewAny', [User::class, $organization]);

        $users = User::query()
            ->whereHas('organizationRoles', fn ($query) => $query->where('organization_id', $organization->id))
            ->with(['organizationRoles' => fn ($query) => $query->where('organization_id', $organization->id)->with('role')])
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::query()->forOrganization($organization->id)->orderBy('name')->get(),
        ]);
    }

    public function store(
        StoreOrganizationUserRequest $request,
        Organization $organization,
        AssignOrganizationRoleAction $assign,
    ): RedirectResponse {
        $data = $request->validated();

        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'person_id' => $data['person_id'] ?? null,
            ]
        );

        $role = Role::query()
            ->forOrganization($organization->id)
            ->whereKey($data['role_id'])
            ->firstOrFail();

        $assign->execute($organization, $user, $role, $request->user());

        return back()->with('success', 'Usuario agregado al club.');
    }
}
