<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('/icons/icon-192.png', false);
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_authenticated_users_are_redirected_from_login_without_dashboard_url_error(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-cartagena']);
        $permissions->createOrganizationRoles($club);

        $member = User::factory()->create([
            'current_organization_id' => $club->id,
        ]);
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        $permissions->assignRole($club, $member, $role);

        $this->actingAs($member)
            ->get('/login')
            ->assertRedirect(route('dashboard', $club));
    }

    public function test_authenticated_platform_admin_is_redirected_from_login_to_clubs(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect(route('organizations.index'));
    }
}
