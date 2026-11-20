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

class PersonSummaryUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_cannot_edit_the_ficha_summary(): void
    {
        [$club, $director, $copilot, $pilot] = $this->club();
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        $member = User::factory()->create(['person_id' => $copilot->id]);
        app(PermissionService::class)->assignRole($club, $member, $memberRole);

        $this->actingAs($member)
            ->get("/app/{$club->slug}/people/{$copilot->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canUpdateSummary', false)
                ->where('pilotChoices', []));

        $this->actingAs($member)
            ->put("/app/{$club->slug}/people/{$copilot->id}/resumen", $this->payload($copilot, $pilot->id))
            ->assertForbidden();
    }

    public function test_an_editor_can_update_identity_contact_church_health_and_the_pilot(): void
    {
        [$club, $director, $copilot, $pilot] = $this->club();
        $replacement = Person::factory()->create([
            'first_name' => 'Nora',
            'last_name' => 'Diaz',
            'status' => PersonStatus::Active,
        ]);
        $replacement->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
            'vehicle_role' => 'driver',
        ]);
        $copilot->motorcycles()->create([
            'plate' => 'XZZ01G',
            'brand' => 'Hero',
            'model' => 'Hunk',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$copilot->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canUpdateSummary', true)
                ->where('pilotChoices', fn ($choices) => collect($choices)->pluck('id')->sort()->values()->all()
                    === collect([$pilot->id, $replacement->id])->sort()->values()->all()));

        $this->actingAs($director)
            ->put("/app/{$club->slug}/people/{$copilot->id}/resumen", [
                ...$this->payload($copilot, $replacement->id),
                'first_name' => 'Camila',
                'phone' => '3001112233',
                'metadata' => [
                    'church_name' => 'Iglesia Central',
                    'church_district' => 'Norte',
                    'church_role' => 'Joven',
                    'baptized' => true,
                    'baptized_at' => '2010-05-01',
                ],
                'health' => [
                    'eps' => 'Sanitas',
                    'blood_type' => 'O',
                    'rh' => 'positivo',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $copilot->refresh()->load(['healthProfile', 'memberships', 'motorcycles']);
        $membership = $copilot->memberships->first();

        $this->assertSame('Camila', $copilot->first_name);
        $this->assertSame('3001112233', $copilot->phone);
        $this->assertSame('Sanitas', $copilot->healthProfile?->eps);
        $this->assertSame('Iglesia Central', $membership?->metadata['church_name'] ?? null);
        $this->assertSame($replacement->id, $membership?->metadata['pilot_person_id'] ?? null);
        $this->assertSame('XZZ01G', $copilot->motorcycles->first()?->plate);

        $this->actingAs($director)
            ->put("/app/{$club->slug}/people/{$copilot->id}/resumen", $this->payload($copilot->fresh(), 'piloto-inexistente'))
            ->assertSessionHasErrors('pilot_person_id');
    }

    /**
     * @return array{0: Organization, 1: User, 2: Person, 3: Person}
     */
    private function club(): array
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-ficha']);
        $permissions->createOrganizationRoles($club);

        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($club, $director, $role);

        $pilot = Person::factory()->create([
            'first_name' => 'Luis',
            'last_name' => 'Castro',
            'status' => PersonStatus::Active,
        ]);
        $pilot->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
            'vehicle_role' => 'driver',
        ]);

        $copilot = Person::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'status' => PersonStatus::Active,
        ]);
        $copilot->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
            'vehicle_role' => 'passenger',
            'metadata' => ['pilot_person_id' => $pilot->id],
        ]);

        return [$club, $director, $copilot, $pilot];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Person $person, string $pilotId): array
    {
        return [
            'document_type' => $person->document_type,
            'document_number' => $person->document_number,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'phone' => $person->phone,
            'vehicle_role' => 'passenger',
            'pilot_person_id' => $pilotId,
            'metadata' => ['baptized' => false],
        ];
    }
}
