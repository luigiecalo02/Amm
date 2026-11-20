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

class DocumentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_index_groups_by_member_and_by_type(): void
    {
        $this->seed(DocumentTypeSeeder::class);

        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-docs']);
        $permissions->createOrganizationRoles($club);

        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($club, $director, $role);

        $person = Person::factory()->create([
            'first_name' => 'Luis',
            'last_name' => 'Castro',
            'document_number' => '1143354429',
        ]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
        ]);

        $licencia = DocumentType::query()->where('slug', 'licencia')->firstOrFail();
        $document = Document::query()->create([
            'document_type_id' => $licencia->id,
            'person_id' => $person->id,
            'document_number' => '1143354429',
            'expiration_date' => '2027-05-24',
            'status' => DocumentStatus::Valid,
        ]);
        $file = $document->files()->create([
            'disk' => 'documents',
            'path' => $document->id.'/licencia.jpg',
            'filename' => 'licencia.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'checksum' => hash('sha256', 'fake'),
            'created_at' => now(),
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/documents")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->has('members', 1)
                ->where('members.0.name', 'Luis Castro')
                ->where('members.0.documents.0.slug', 'licencia')
                ->where('members.0.documents.0.expiration_date', '2027-05-24')
                ->where('members.0.documents.0.files.0.url', route('documents.download', [$club, $document->id, $file->id]))
                ->has('types')
                ->where('types.1.slug', 'licencia')
                ->where('types.1.count', 1)
                ->where('types.1.entries.0.member.name', 'Luis Castro')
                ->where('types.1.entries.0.document_number', '1143354429'));
    }
}
