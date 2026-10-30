<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationService
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function create(array $data, User $owner): Organization
    {
        return DB::transaction(function () use ($data, $owner): Organization {
            $organization = Organization::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'type' => $data['type'] ?? 'motorcycle_club',
                'description' => $data['description'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? 'CO',
                'department' => $data['department'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => OrganizationStatus::Active,
                'settings' => $data['settings'] ?? [],
            ]);

            $this->permissions->createOrganizationRoles($organization);

            if (! $owner->isSuperAdmin()) {
                $director = Role::query()
                    ->where('organization_id', $organization->id)
                    ->where('slug', 'director')
                    ->firstOrFail();

                $this->permissions->assignRole($organization, $owner, $director);

                $owner->forceFill([
                    'current_organization_id' => $organization->id,
                ])->save();
            }

            return $organization;
        });
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->fill($data);
        $organization->save();

        return $organization->refresh();
    }
}
