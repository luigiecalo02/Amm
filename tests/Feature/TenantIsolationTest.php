<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_a_person_from_another_organization(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $clubA = Organization::factory()->create(['slug' => 'club-a']);
        $clubB = Organization::factory()->create(['slug' => 'club-b']);
        $permissions->createOrganizationRoles($clubA);
        $permissions->createOrganizationRoles($clubB);

        $user = User::factory()->create();
        $director = Role::query()->where('organization_id', $clubA->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($clubA, $user, $director);

        $foreign = Person::factory()->create();
        $foreign->memberships()->create([
            'organization_id' => $clubB->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/app/{$clubA->slug}/people/{$foreign->id}")
            ->assertNotFound();
    }
}
