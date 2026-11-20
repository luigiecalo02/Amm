<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorcycleIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_plates_include_the_pilot_and_copilot_for_the_flip_side(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-placas']);
        $permissions->createOrganizationRoles($club);
        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($club, $director, $role);

        $pilot = Person::factory()->create([
            'first_name' => 'Angel',
            'last_name' => 'Doria',
            'status' => PersonStatus::Active,
        ]);
        $pilot->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
            'vehicle_role' => 'driver',
        ]);
        $motorcycle = $pilot->motorcycles()->create([
            'plate' => 'MOP06G',
            'brand' => 'Hero',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $copilot = Person::factory()->create([
            'first_name' => 'Daniela',
            'last_name' => 'Diaz',
            'status' => PersonStatus::Active,
        ]);
        $copilot->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
            'vehicle_role' => 'passenger',
            'metadata' => ['pilot_person_id' => $pilot->id],
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/motorcycles")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Motorcycles/Index')
                ->where('motorcycles.data.0.id', $motorcycle->id)
                ->where('motorcycles.data.0.riders.0.role', 'Piloto')
                ->where('motorcycles.data.0.riders.0.name', $pilot->full_name)
                ->where('motorcycles.data.0.riders.1.role', 'Copiloto')
                ->where('motorcycles.data.0.riders.1.name', $copilot->full_name));
    }
}
