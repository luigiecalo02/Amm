<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationRoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_all_clubs_and_club_user_sees_only_theirs(): void
    {
        [$permissions, $cartagena, $medellin] = $this->seedClubs();

        $superAdmin = User::factory()->create(['is_platform_admin' => true]);
        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $cartagena->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($cartagena, $director, $role);

        $this->actingAs($superAdmin)
            ->get('/organizations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Organizations/Index')
                ->has('organizations', 2)
                ->where('canCreate', true));

        $this->actingAs($director)
            ->get('/organizations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('organizations', 1)
                ->where('organizations.0.slug', 'amm-cartagena')
                ->where('canCreate', false));

        $this->actingAs($director)
            ->get("/app/{$medellin->slug}")
            ->assertForbidden();
    }

    public function test_club_member_cannot_open_users_or_roles_without_permission(): void
    {
        [$permissions, $cartagena] = $this->seedClubs();

        $member = User::factory()->create();
        $role = Role::query()->where('organization_id', $cartagena->id)->where('slug', 'miembro')->firstOrFail();
        $permissions->assignRole($cartagena, $member, $role);

        $this->actingAs($member)->get("/app/{$cartagena->slug}/people")->assertOk();
        $this->actingAs($member)->get("/app/{$cartagena->slug}/users")->assertForbidden();
        $this->actingAs($member)->get("/app/{$cartagena->slug}/roles")->assertForbidden();
        $this->actingAs($member)->post('/organizations', ['name' => 'Otro club'])->assertForbidden();
    }

    public function test_each_club_has_a_superadministrador_role_with_all_permissions(): void
    {
        [$permissions, $cartagena] = $this->seedClubs();

        $role = Role::query()
            ->where('organization_id', $cartagena->id)
            ->where('slug', Role::CLUB_SUPER_ADMIN_SLUG)
            ->with('permissions')
            ->first();

        $this->assertNotNull($role);
        $this->assertSame('Superadministrador', $role->name);
        $this->assertCount(
            count(\App\Support\PermissionCatalog::slugs()),
            $role->permissions
        );
    }

    public function test_super_admin_is_not_assigned_to_a_new_club(): void
    {
        app(PermissionService::class)->syncCatalog();

        $superAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($superAdmin)
            ->post('/organizations', [
                'name' => 'Club AMM Barranquilla',
                'city' => 'Barranquilla',
            ])
            ->assertRedirect();

        $this->assertFalse($superAdmin->organizationRoles()->exists());
    }

    /**
     * @return array{0: PermissionService, 1: Organization, 2?: Organization}
     */
    private function seedClubs(): array
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $cartagena = Organization::factory()->create(['slug' => 'amm-cartagena', 'name' => 'AMM Cartagena']);
        $medellin = Organization::factory()->create(['slug' => 'amm-medellin', 'name' => 'AMM Medellín']);
        $permissions->createOrganizationRoles($cartagena);
        $permissions->createOrganizationRoles($medellin);

        return [$permissions, $cartagena, $medellin];
    }
}
