<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Services\Permissions\PermissionService;
use App\Support\PermissionCatalog;
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
            ->orderByRaw("CASE WHEN slug = ? THEN 0 ELSE 1 END", [Role::CLUB_SUPER_ADMIN_SLUG])
            ->orderBy('name')
            ->get(),
            'catalog' => $this->permissions->permissionsByModule(),
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', [Role::class, $organization]);

        return Inertia::render('Roles/Create', [
            'catalog' => $this->permissions->permissionsByModule(),
        ]);
    }

    public function store(StoreRoleRequest $request, Organization $organization): RedirectResponse
    {
        $this->permissions->createRole($organization, $request->validated());

        return redirect()
            ->route('roles.index', $organization)
            ->with('success', 'Rol creado.');
    }

    public function update(UpdateRoleRequest $request, Organization $organization, Role $role): RedirectResponse
    {
        abort_unless($role->organization_id === $organization->id, 404);

        if ($role->isClubSuperAdmin()) {
            $this->permissions->syncRolePermissions($role, PermissionCatalog::slugs());

            return back()->with('success', 'El superadministrador conserva todos los permisos.');
        }

        $role->update($request->safe()->only(['name', 'description']));
        $this->permissions->syncRolePermissions($role, $request->validated('permissions') ?? []);

        return back()->with('success', 'Rol actualizado.');
    }
}
