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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_and_updates_only_their_documents(): void
    {
        Storage::fake('documents');
        $this->seed(DocumentTypeSeeder::class);
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-mios']);
        $permissions->createOrganizationRoles($club);
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();

        $owner = $this->member($club, $memberRole, 'ana@amm.test', 'Ana', 'Perez');
        $other = $this->member($club, $memberRole, 'otro@amm.test', 'Luis', 'Castro');

        $licencia = DocumentType::query()->where('slug', 'licencia')->firstOrFail();
        $mine = Document::query()->create([
            'document_type_id' => $licencia->id,
            'person_id' => $owner->person_id,
            'document_number' => '100',
            'expiration_date' => '2027-01-01',
            'status' => DocumentStatus::Valid,
        ]);
        Document::query()->create([
            'document_type_id' => $licencia->id,
            'person_id' => $other->person_id,
            'document_number' => '200',
            'expiration_date' => '2027-02-02',
            'status' => DocumentStatus::Valid,
        ]);

        $this->actingAs($owner)
            ->get("/app/{$club->slug}/mis-documentos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Mine')
                ->where('linked', true)
                ->where('name', 'Ana Perez')
                ->where('photo_url', null)
                ->where('types.0.slug', 'cedula')
                ->where('types.0.count', 0)
                ->where('types.1.slug', 'licencia')
                ->where('types.1.count', 1)
                ->where('types.1.entries.0.document_number', '100'));

        $this->actingAs($owner)
            ->put("/app/{$club->slug}/mis-documentos/{$mine->id}", [
                'slug' => 'licencia',
                'document_number' => '100-B',
                'expiration_date' => '2028-03-03',
                'files' => [UploadedFile::fake()->image('licencia.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $mine->refresh();
        $this->assertSame('100-B', $mine->document_number);
        $this->assertSame('2028-03-03', $mine->expiration_date?->toDateString());
        $this->assertSame(1, $mine->files()->count());

        $foreign = Document::query()->where('person_id', $other->person_id)->firstOrFail();
        $this->actingAs($owner)
            ->put("/app/{$club->slug}/mis-documentos/{$foreign->id}", [
                'slug' => 'licencia',
                'document_number' => 'hack',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post("/app/{$club->slug}/mis-documentos", [
                'slug' => 'soat',
                'expiration_date' => '2027-06-01',
            ])
            ->assertStatus(422);

        $soat = DocumentType::query()->where('slug', 'soat')->firstOrFail();
        $motorcycle = $owner->person->motorcycles()->create([
            'plate' => 'MOP06G',
            'brand' => 'AKT',
        ]);

        $this->actingAs($owner)
            ->post("/app/{$club->slug}/mis-documentos", [
                'slug' => 'soat',
                'motorcycle_id' => $motorcycle->id,
                'expiration_date' => '2027-06-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $created = Document::query()->where('motorcycle_id', $motorcycle->id)->where('document_type_id', $soat->id)->first();
        $this->assertNotNull($created);
        $this->assertSame('2027-06-01', $created->expiration_date?->toDateString());
    }

    public function test_a_member_can_replace_one_side_without_wiping_the_other(): void
    {
        Storage::fake('documents');
        $this->seed(DocumentTypeSeeder::class);
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-lados']);
        $permissions->createOrganizationRoles($club);
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        $owner = $this->member($club, $memberRole, 'lados@amm.test', 'Ana', 'Perez');
        $cedula = DocumentType::query()->where('slug', 'cedula')->firstOrFail();

        $this->actingAs($owner)
            ->post("/app/{$club->slug}/mis-documentos", [
                'slug' => 'cedula',
                'document_number' => '1047475332',
                'file' => UploadedFile::fake()->image('cara.jpg'),
                'side' => 'front',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document = Document::query()
            ->where('person_id', $owner->person_id)
            ->where('document_type_id', $cedula->id)
            ->firstOrFail();

        $this->assertSame(1, $document->files()->count());
        $this->assertSame('front', $document->files()->first()?->side);
        $this->assertSame('cara.jpg', $document->files()->first()?->filename);

        $this->actingAs($owner)
            ->put("/app/{$club->slug}/mis-documentos/{$document->id}", [
                'slug' => 'cedula',
                'file' => UploadedFile::fake()->image('respaldo.jpg'),
                'side' => 'back',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame(2, $document->files()->count());
        $this->assertSame('cara.jpg', $document->files()->where('side', 'front')->value('filename'));
        $this->assertSame('respaldo.jpg', $document->files()->where('side', 'back')->value('filename'));

        $this->actingAs($owner)
            ->put("/app/{$club->slug}/mis-documentos/{$document->id}", [
                'slug' => 'cedula',
                'file' => UploadedFile::fake()->image('cara-nueva.jpg'),
                'side' => 'front',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, $document->files()->count());
        $this->assertSame('cara-nueva.jpg', $document->files()->where('side', 'front')->value('filename'));
        $this->assertSame('respaldo.jpg', $document->files()->where('side', 'back')->value('filename'));
    }

    public function test_a_club_user_without_a_member_profile_sees_the_unlinked_state(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-sin-ficha']);
        $permissions->createOrganizationRoles($club);
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'secretario')->firstOrFail();
        $user = User::factory()->create(['person_id' => null]);
        $permissions->assignRole($club, $user, $role);

        $this->actingAs($user)
            ->get("/app/{$club->slug}/mis-documentos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Mine')
                ->where('linked', false)
                ->where('photo_url', null)
                ->where('types', []));
    }

    public function test_a_member_sees_their_portrait_on_own_documents(): void
    {
        Storage::fake('documents');
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-foto']);
        $permissions->createOrganizationRoles($club);
        $memberRole = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        $owner = $this->member($club, $memberRole, 'foto@amm.test', 'Ana', 'Perez');
        $path = "photos/people/{$owner->person_id}/photo.jpg";
        Storage::disk('documents')->put($path, 'portrait');
        $owner->person->forceFill(['photo' => $path])->save();

        $this->actingAs($owner)
            ->get("/app/{$club->slug}/mis-documentos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('photo_url', route('profile.photo', $club)));
    }

    private function member(Organization $club, Role $role, string $email, string $firstName, string $lastName): User
    {
        $person = Person::factory()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
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
