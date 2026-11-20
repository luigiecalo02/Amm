<?php

namespace App\Services\Permissions;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionService
{
    public function userCan(User $user, string $permission, Organization|string|null $organization = null): bool
    {
        return $user->hasPermission($permission, $organization);
    }

    public function syncCatalog(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }

    public function createOrganizationRoles(Organization $organization): void
    {
        $permissions = Permission::query()->pluck('id', 'slug');

        foreach ($this->defaultRoles() as $definition) {
            $role = $this->firstOrCreateDefaultRole($organization, $definition);
            $this->grant($role, $definition['permissions'], $permissions);
        }
    }

    public function ensureMissingSystemRoles(Organization $organization): void
    {
        $permissions = Permission::query()->pluck('id', 'slug');

        foreach ($this->defaultRoles() as $definition) {
            $role = $this->firstOrCreateDefaultRole($organization, $definition);

            if ($role->wasRecentlyCreated) {
                $this->grant($role, $definition['permissions'], $permissions);
            }
        }
    }

    /**
     * @return list<array{name: string, slug: string, description?: string, permissions: array<int, string>}>
     */
    private function defaultRoles(): array
    {
        return [
            [
                'name' => 'Superadministrador',
                'slug' => Role::CLUB_SUPER_ADMIN_SLUG,
                'description' => 'Tiene todos los permisos del club',
                'permissions' => PermissionCatalog::superAdminDefaults(),
            ],
            ['name' => 'Director', 'slug' => 'director', 'permissions' => PermissionCatalog::directorDefaults()],
            ['name' => 'Subdirector', 'slug' => 'subdirector', 'permissions' => PermissionCatalog::directorDefaults()],
            ['name' => 'Secretario', 'slug' => 'secretario', 'permissions' => PermissionCatalog::secretaryDefaults()],
            ['name' => 'Tesorero', 'slug' => 'tesorero', 'permissions' => PermissionCatalog::treasurerDefaults()],
            ['name' => 'Capellán', 'slug' => 'capellan', 'description' => 'Acompaña al club y recibe los avisos de la directiva', 'permissions' => PermissionCatalog::chaplainDefaults()],
            ['name' => 'Miembro', 'slug' => 'miembro', 'permissions' => PermissionCatalog::memberDefaults()],
        ];
    }

    /**
     * @param  array{name: string, slug: string, description?: string, permissions: array<int, string>}  $definition
     */
    private function firstOrCreateDefaultRole(Organization $organization, array $definition): Role
    {
        return Role::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => $definition['slug'],
            ],
            [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? 'Rol inicial del club',
                'is_system' => true,
            ]
        );
    }

    /**
     * @param  array<int, string>  $slugs
     * @param  Collection<string, string>  $permissions
     */
    private function grant(Role $role, array $slugs, Collection $permissions): void
    {
        $role->permissions()->sync(
            collect($slugs)->map(fn (string $slug) => $permissions[$slug] ?? null)->filter()->all()
        );
    }

    public function createRole(Organization $organization, array $data): Role
    {
        return DB::transaction(function () use ($organization, $data): Role {
            $role = Role::query()->create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            if (! empty($data['permissions'])) {
                $this->syncRolePermissions($role, $data['permissions']);
            }

            return $role;
        });
    }

    public function syncRolePermissions(Role $role, array $permissionSlugs): Role
    {
        $ids = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id');

        $role->permissions()->sync($ids);

        return $role->refresh()->load('permissions');
    }

    public function grantDefaultPermission(string $slug): void
    {
        $permissionId = Permission::query()->where('slug', $slug)->value('id');

        if (! is_string($permissionId)) {
            return;
        }

        foreach (Organization::query()->get() as $organization) {
            foreach ($this->defaultRoles() as $definition) {
                if (! in_array($slug, $definition['permissions'], true)) {
                    continue;
                }

                $role = Role::query()
                    ->where('organization_id', $organization->id)
                    ->where('slug', $definition['slug'])
                    ->first();

                $role?->permissions()->syncWithoutDetaching([$permissionId]);
            }
        }
    }

    public function assignRole(Organization $organization, User $user, Role $role): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $organization->userRoles()
            ->where('user_id', $user->id)
            ->where('role_id', '!=', $role->id)
            ->delete();

        $organization->userRoles()->firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function permissionsByModule(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
    }
}
