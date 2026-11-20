<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_their_profile_tabs_without_documents(): void
    {
        $this->seed(DocumentTypeSeeder::class);
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-perfil']);
        $permissions->createOrganizationRoles($club);
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();

        $user = $this->member($club, $memberRole, 'ana@amm.test', [
            'first_name' => 'Luis',
            'middle_name' => 'Guillermo',
            'last_name' => 'Castro',
            'second_last_name' => 'Lora',
            'document_type' => 'cc',
            'document_number' => '1143354429',
        ]);

        $licencia = DocumentType::query()->where('slug', 'licencia')->firstOrFail();
        Document::query()->create([
            'document_type_id' => $licencia->id,
            'person_id' => $user->person_id,
            'document_number' => '1143354429',
            'expiration_date' => '2028-03-15',
            'status' => DocumentStatus::Valid,
        ]);

        $this->actingAs($user)
            ->get("/app/{$club->slug}/perfil")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Show')
                ->where('linked', true)
                ->where('name', 'Luis Guillermo Castro Lora')
                ->where('photo_url', null)
                ->where('form.first_name', 'Luis')
                ->where('form.middle_name', 'Guillermo')
                ->where('form.last_name', 'Castro')
                ->where('form.second_last_name', 'Lora')
                ->where('form.document_type', 'cc')
                ->where('form.document_number', '1143354429')
                ->where('form.license_document_number', '1143354429')
                ->where('form.license_expiration', '2028-03-15')
                ->where('person.document_number', '1143354429')
                ->has('person.motorcycles')
                ->missing('alertPreferences')
                ->missing('documents')
                ->missing('types'));
    }

    public function test_an_unlinked_user_sees_an_empty_profile(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-sin-ficha']);
        $permissions->createOrganizationRoles($club);
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'sin-ficha@amm.test',
            'name' => 'Sin Ficha',
            'person_id' => null,
        ]);
        $permissions->assignRole($club, $user, $memberRole);

        $this->actingAs($user)
            ->get("/app/{$club->slug}/perfil")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Show')
                ->where('linked', false)
                ->where('photo_url', null)
                ->where('form', null));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(Organization $club, Role $role, string $email, array $attributes): User
    {
        $person = Person::factory()->create([
            ...$attributes,
            'email' => $email,
        ]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
        ]);
        $user = User::factory()->create([
            'email' => $email,
            'person_id' => $person->id,
        ]);
        app(PermissionService::class)->assignRole($club, $user, $role);

        return $user;
    }
}
