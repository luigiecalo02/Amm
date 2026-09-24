<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Services\Permissions\PermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function index(Organization $organization): Response
    {
        $this->authorize('viewAny', [Role::class, $organization]);

        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->forOrganization($organization->id)
                ->with('permissions')
                ->orderBy('name')
                ->get(),
            'permissions' => $this->permissions->permissionsByModule(),
        ]);
    }

    public function store(StoreRoleRequest $request, Organization $organization): RedirectResponse
    {
        $this->permissions->createRole($organization, $request->validated());

        return back()->with('success', 'Rol creado.');
    }

    public function update(UpdateRoleRequest $request, Organization $organization, Role $role): RedirectResponse
    {
        abort_unless($role->organization_id === $organization->id, 404);

        $role->update($request->safe()->only(['name', 'description']));
        $this->permissions->syncRolePermissions($role, $request->validated('permissions') ?? []);

        return back()->with('success', 'Rol actualizado.');
    }
}
