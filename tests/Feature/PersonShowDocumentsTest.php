<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonShowDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_ficha_shows_tabs_without_documents(): void
    {
        Storage::fake('documents');

        $this->seed(DocumentTypeSeeder::class);

        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-test']);
        $permissions->createOrganizationRoles($club);

        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($club, $director, $role);

        $person = Person::factory()->create();
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $cedula = DocumentType::query()->where('slug', 'cedula')->firstOrFail();
        $document = Document::query()->create([
            'document_type_id' => $cedula->id,
            'person_id' => $person->id,
            'document_number' => '1047475332',
            'status' => DocumentStatus::Valid,
        ]);

        Storage::disk('documents')->put($document->id.'/cedula.jpg', 'fake-image');
        $file = $document->files()->create([
            'disk' => 'documents',
            'path' => $document->id.'/cedula.jpg',
            'filename' => 'cedula.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'checksum' => hash('sha256', 'fake-image'),
            'created_at' => now(),
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$person->id}")
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('People/Show')
                ->where('canUpdateProfile', true)
                ->where('form.document_number', $person->document_number)
                ->has('form.motorcycle')
                ->missing('documents')
                ->missing('canDownload'));

        $this->actingAs($director)
            ->get("/app/{$club->slug}/documents/{$document->id}/files/{$file->id}")
            ->assertOk();
    }

    public function test_a_member_does_not_get_the_edit_action_on_a_ficha(): void
    {
        [$club, $director, $person] = $this->member(PersonStatus::Active, MembershipStatus::Active);
        $permissions = app(PermissionService::class);
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        $member = User::factory()->create(['person_id' => $person->id]);
        $permissions->assignRole($club, $member, $memberRole);

        $this->actingAs($member)
            ->get("/app/{$club->slug}/people/{$person->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canUpdateProfile', false));

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$person->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canUpdateProfile', true));
    }

    public function test_photos_stay_locked_until_the_member_is_active(): void
    {
        Storage::fake('documents');

        [$club, $director, $person] = $this->member(PersonStatus::PendingReview, MembershipStatus::Pending);
        $motorcycle = $person->motorcycles()->create([
            'plate' => 'XYZ99A',
            'brand' => 'Hero',
            'model' => 'Xpulse',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $this->actingAs($director)
            ->post("/app/{$club->slug}/people/{$person->id}/fotos", [
                'photo' => UploadedFile::fake()->image('perfil.jpg'),
                'motorcycle_id' => $motorcycle->id,
                'motorcycle_photo' => UploadedFile::fake()->image('moto.jpg'),
            ])
            ->assertSessionHasErrors('status');

        $this->assertNull($person->fresh()->photo);
        $this->assertNull($motorcycle->fresh()->photo);
    }

    public function test_an_active_member_can_update_profile_and_motorcycle_photos(): void
    {
        Storage::fake('documents');

        [$club, $director, $person] = $this->member(PersonStatus::Active, MembershipStatus::Active);
        $motorcycle = $person->motorcycles()->create([
            'plate' => 'XYZ99A',
            'brand' => 'Hero',
            'model' => 'Xpulse',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $this->actingAs($director)
            ->post("/app/{$club->slug}/people/{$person->id}/fotos", [
                'photo' => UploadedFile::fake()->image('perfil.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($director)
            ->post("/app/{$club->slug}/people/{$person->id}/fotos", [
                'motorcycle_id' => $motorcycle->id,
                'motorcycle_photo' => UploadedFile::fake()->image('moto.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $person->refresh();
        $motorcycle->refresh();
        $this->assertNotNull($person->photo);
        $this->assertNotNull($motorcycle->photo);
        Storage::disk('documents')->assertExists($person->photo);
        Storage::disk('documents')->assertExists($motorcycle->photo);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$person->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('memberActive', true)
                ->where('person.photo_url', route('people.photo', [$club, $person]))
                ->where('person.motorcycles.0.photo_url', route('people.motorcycles.photo', [$club, $person, $motorcycle])));

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$person->id}/foto")
            ->assertOk();

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$person->id}/motos/{$motorcycle->id}/foto")
            ->assertOk();
    }

    public function test_a_copilot_ficha_shows_the_pilots_motorcycle_banner(): void
    {
        Storage::fake('documents');

        [$club, $director, $pilot] = $this->member(PersonStatus::Active, MembershipStatus::Active);
        $motorcycle = $pilot->motorcycles()->create([
            'plate' => 'AAQ57G',
            'brand' => 'Hero',
            'model' => 'Hunk',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);
        $path = "photos/motorcycles/{$motorcycle->id}/photo.jpg";
        Storage::disk('documents')->put($path, 'moto');
        $motorcycle->forceFill(['photo' => $path])->save();

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
            ->get("/app/{$club->slug}/people/{$copilot->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('person.motorcycles.0.id', $motorcycle->id)
                ->where('person.motorcycles.0.photo_url', route('people.motorcycles.photo', [$club, $pilot, $motorcycle]))
                ->where('canUpdateMotorcycle', false)
                ->where('form.vehicle_role', 'passenger')
                ->where('form.pilot_person_id', $pilot->id)
                ->where('pilots.0.id', $pilot->id)
                ->where('pilots.0.name', $pilot->full_name)
                ->where('pilots.0.document_number', $pilot->document_number));

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$pilot->id}/motos/{$motorcycle->id}/foto")
            ->assertOk();
    }

    /**
     * @return array{0: Organization, 1: User, 2: Person}
     */
    private function member(PersonStatus $personStatus, MembershipStatus $membershipStatus): array
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-fotos']);
        $permissions->createOrganizationRoles($club);

        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($club, $director, $role);

        $person = Person::factory()->create(['status' => $personStatus]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => $membershipStatus,
            'joined_at' => now()->toDateString(),
        ]);

        return [$club, $director, $person];
    }
}
