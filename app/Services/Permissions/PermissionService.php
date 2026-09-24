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
        $defaults = [
            ['name' => 'Director', 'slug' => 'director', 'permissions' => PermissionCatalog::directorDefaults()],
            ['name' => 'Subdirector', 'slug' => 'subdirector', 'permissions' => PermissionCatalog::directorDefaults()],
            ['name' => 'Secretario', 'slug' => 'secretario', 'permissions' => PermissionCatalog::secretaryDefaults()],
            ['name' => 'Tesorero', 'slug' => 'tesorero', 'permissions' => PermissionCatalog::treasurerDefaults()],
            ['name' => 'Miembro', 'slug' => 'miembro', 'permissions' => PermissionCatalog::memberDefaults()],
        ];

        $permissions = Permission::query()->pluck('id', 'slug');

        foreach ($defaults as $definition) {
            $role = Role::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $definition['slug'],
                ],
                [
                    'name' => $definition['name'],
                    'description' => 'Rol inicial del club',
                    'is_system' => true,
                ]
            );

            $role->permissions()->sync(
                collect($definition['permissions'])->map(fn (string $slug) => $permissions[$slug] ?? null)->filter()->all()
            );
        }
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

    public function assignRole(Organization $organization, User $user, Role $role): void
    {
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
