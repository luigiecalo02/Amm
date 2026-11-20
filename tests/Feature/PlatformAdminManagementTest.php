<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_open_people_users_and_roles(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-test']);
        $permissions->createOrganizationRoles($club);

        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->get("/app/{$club->slug}/people")->assertOk();
        $this->actingAs($admin)->get("/app/{$club->slug}/users")->assertOk();
        $this->actingAs($admin)->get("/app/{$club->slug}/roles")->assertOk();
        $this->actingAs($admin)->get("/app/{$club->slug}/settings")->assertOk();
    }

    public function test_platform_admin_can_update_a_role_and_assign_it(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-test']);
        $permissions->createOrganizationRoles($club);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $member = User::factory()->create();
        $director = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $secretary = Role::query()->where('organization_id', $club->id)->where('slug', 'secretario')->firstOrFail();

        $permissions->assignRole($club, $member, $director);

        $this->actingAs($admin)
            ->put("/app/{$club->slug}/roles/{$secretary->id}", [
                'name' => $secretary->name,
                'description' => 'Permisos del secretario',
                'permissions' => ['people.view', 'users.view'],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put("/app/{$club->slug}/users/{$member->id}", [
                'role_id' => $secretary->id,
            ])
            ->assertRedirect();

        $this->assertTrue(
            $member->organizationRoles()
                ->where('organization_id', $club->id)
                ->where('role_id', $secretary->id)
                ->exists()
        );
    }

    public function test_an_admin_can_change_the_email_and_password_used_to_sign_in(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-acceso']);
        $permissions->createOrganizationRoles($club);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $member = User::factory()->create(['email' => 'antes@amm.test']);
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        $permissions->assignRole($club, $member, $role);

        $this->actingAs($admin)
            ->put("/app/{$club->slug}/users/{$member->id}", [
                'role_id' => $role->id,
                'email' => 'nuevo@amm.test',
                'password' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $member->refresh();
        $this->assertSame('nuevo@amm.test', $member->email);
        $this->assertTrue(Hash::check('password', $member->password));

        $this->actingAs($admin)
            ->put("/app/{$club->slug}/users/{$member->id}", [
                'role_id' => $role->id,
                'email' => 'nuevo@amm.test',
                'password' => 'clave-nueva',
            ])
            ->assertRedirect();

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'nuevo@amm.test',
            'password' => 'clave-nueva',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($member);

        $other = User::factory()->create(['email' => 'ocupado@amm.test']);
        $permissions->assignRole($club, $other, $role);

        $this->actingAs($admin)
            ->put("/app/{$club->slug}/users/{$member->id}", [
                'role_id' => $role->id,
                'email' => 'ocupado@amm.test',
                'password' => '123',
            ])
            ->assertSessionHasErrors(['email', 'password']);
    }
}
