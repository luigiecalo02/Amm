<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\InscriptionApplicationStatus;
use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\InscriptionApplication;
use App\Models\InscriptionLink;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Inscriptions\InscriptionFormMapper;
use App\Services\Inscriptions\InscriptionLinkService;
use App\Services\Permissions\PermissionService;
use App\Support\InscriptionDocumentSteps;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InscriptionLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_staff_can_create_a_public_inscription_link(): void
    {
        [$club, $director] = $this->clubWithDirector();

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/links", [
                'name' => 'Jornada abril',
                'expires_in_days' => 15,
            ])
            ->assertRedirect();

        $link = InscriptionLink::query()->first();
        $this->assertNotNull($link);
        $this->assertSame($club->id, $link->organization_id);

        $this->get('/i/'.$link->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/InscriptionBasics'));
    }

    public function test_club_staff_can_delete_a_link_so_nobody_else_can_enroll(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-borrar',
            'name' => 'Formualrio 2026',
            'is_active' => true,
        ]);

        $this->actingAs($director)
            ->delete("/app/{$club->slug}/inscripciones/links/{$link->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('inscription_links', ['id' => $link->id]);
        $this->get('/i/enlace-borrar')->assertNotFound();
    }

    public function test_a_person_can_submit_the_public_form_and_staff_can_grant_access(): void
    {
        Storage::fake('scans');
        Storage::fake('documents');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-prueba-amm',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1122334455',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'vehicle_role' => 'driver',
        ])->assertRedirect("/i/{$link->token}");

        $steps = InscriptionDocumentSteps::keys('driver');
        $last = array_pop($steps);

        foreach ($steps as $step) {
            $this->post("/i/{$link->token}/documentos", [
                'step' => $step,
                'file' => $this->officialImage($step),
            ])->assertRedirect("/i/{$link->token}");
        }

        $this->post("/i/{$link->token}/documentos", [
            'step' => $last,
            'file' => $this->officialImage($last),
        ])->assertRedirect("/i/{$link->token}");

        $this->post("/i/{$link->token}/continuar")->assertRedirect("/i/{$link->token}/formulario");

        $this->post("/i/{$link->token}/formulario", [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'document_type' => 'cc',
            'document_number' => '1122334455',
            'phone' => '3001112233',
            'email' => 'ana@amm.test',
            'city' => 'Cartagena',
            'motorcycle' => ['plate' => 'XYZ99A', 'brand' => 'Hero', 'vehicle_type' => 'motorcycle'],
        ])->assertRedirect("/i/{$link->token}/gracias");

        $application = $link->applications()->where('status', InscriptionApplicationStatus::Submitted)->first();
        $this->assertNotNull($application);
        $this->assertSame($club->id, $application->organization_id);
        $this->assertNotNull($application->reference);
        $this->assertMatchesRegularExpression('/^AMM-\d{4}-\d{4}$/', $application->reference);

        $this->get("/i/{$link->token}/gracias")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionThanks')
                ->where('reference', $application->reference));

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/verificar")
            ->assertRedirect();

        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/acceso", [
                'email' => 'ana@amm.test',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'ana@amm.test', 'person_id' => $application->fresh()->person_id]);
    }

    public function test_uploaded_inscription_files_are_kept_on_the_person(): void
    {
        Storage::fake('scans');
        Storage::fake('documents');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-archivos',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1099887766',
            'first_name' => 'Nora',
            'last_name' => 'Díaz',
            'vehicle_role' => 'driver',
        ])->assertRedirect("/i/{$link->token}");

        foreach (InscriptionDocumentSteps::keys('driver') as $step) {
            $this->post("/i/{$link->token}/documentos", [
                'step' => $step,
                'file' => $this->officialImage($step),
            ])->assertRedirect("/i/{$link->token}");
        }

        $this->post("/i/{$link->token}/continuar")->assertRedirect("/i/{$link->token}/formulario");

        $this->post("/i/{$link->token}/formulario", [
            'first_name' => 'Nora',
            'last_name' => 'Díaz',
            'document_type' => 'cc',
            'document_number' => '1099887766',
            'phone' => '3009998877',
            'email' => 'nora@amm.test',
            'city' => 'Cartagena',
            'motorcycle' => ['plate' => 'NOR01A', 'brand' => 'Honda', 'vehicle_type' => 'motorcycle'],
        ])->assertRedirect("/i/{$link->token}/gracias");

        $person = Person::query()->where('document_number', '1099887766')->first();
        $this->assertNotNull($person);
        $this->assertGreaterThan(0, $person->documents()->count());
        $this->assertGreaterThan(0, $person->documents()->with('files')->get()->sum(fn ($document) => $document->files->count()));
        $this->assertGreaterThan(0, $person->motorcycles()->with('documents.files')->first()?->documents->sum(fn ($document) => $document->files->count()));

        $this->actingAs($director)
            ->get("/app/{$club->slug}/people/{$person->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('People/Show')
                ->has('documents')
                ->has('documents.0.files.0.filename'));
    }

    public function test_empty_draft_applications_are_hidden_from_the_staff_list(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-lista',
            'name' => 'Lista',
            'is_active' => true,
        ]);

        InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::Draft,
            'extracted_data' => [],
            'uploads' => [],
        ]);

        InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::Draft,
            'email' => 'borrador@amm.test',
            'form_draft' => ['first_name' => 'Luisa', 'last_name' => 'Mora'],
            'extracted_data' => [],
            'uploads' => [],
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/inscripciones")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inscriptions/Index')
                ->has('applications', 1)
                ->where('applications.0.display_name', 'Luisa Mora')
                ->where('applications.0.status', 'draft'));
    }

    public function test_the_same_document_number_reuses_a_single_application(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-cedula-unica',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1.047.475.332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'vehicle_role' => 'driver',
        ])->assertRedirect("/i/{$link->token}");

        $this->flushSession();

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie Andrés',
            'last_name' => 'Calo',
            'vehicle_role' => 'driver',
        ])->assertRedirect("/i/{$link->token}");

        $this->assertSame(1, InscriptionApplication::query()->where('document_number', '1047475332')->count());
        $this->assertSame('Luigie Andrés', InscriptionApplication::query()->where('document_number', '1047475332')->value('form_draft')['first_name'] ?? null);
    }

    public function test_staff_can_delete_an_open_application_but_not_a_verified_one(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-borrar-solicitud',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $verified = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::Verified,
            'document_number' => '9988776655',
            'form_draft' => ['first_name' => 'Ana', 'last_name' => 'Pérez'],
            'extracted_data' => [],
            'uploads' => [],
        ]);

        $open = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::ReadyForForm,
            'document_number' => '1047475332',
            'form_draft' => ['first_name' => 'Luigie', 'last_name' => 'Calo', 'document_number' => '1047475332'],
            'extracted_data' => [],
            'uploads' => [],
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/inscripciones")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inscriptions/Index')
                ->has('applications', 2)
                ->where('applications.0.can_delete', true)
                ->where('applications.1.can_delete', false));

        $this->actingAs($director)
            ->delete("/app/{$club->slug}/inscripciones/{$open->id}")
            ->assertRedirect("/app/{$club->slug}/inscripciones");

        $this->assertDatabaseMissing('inscription_applications', ['id' => $open->id]);

        $this->actingAs($director)
            ->delete("/app/{$club->slug}/inscripciones/{$verified->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('inscription_applications', ['id' => $verified->id]);
    }

    public function test_staff_can_see_typed_data_and_uploaded_files_on_an_open_application(): void
    {
        Storage::fake('scans');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-ficha',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $path = UploadedFile::fake()->image('cedula-frente.jpg')->store('inscriptions/open', 'scans');

        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::ReadyForForm,
            'document_number' => '1047475332',
            'email' => 'luigie@amm.test',
            'form_draft' => [
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'document_type' => 'cc',
                'document_number' => '1047475332',
                'phone' => '3001112233',
                'city' => 'Cartagena',
                'vehicle_role' => 'driver',
                'license_document_number' => '1047475332',
                'license_expiration' => '2028-03-15',
                'license_categories' => [['category' => 'A2', 'expires_at' => '2028-03-15']],
            ],
            'extracted_data' => [
                'blood_type' => 'O',
                'rh' => 'positivo',
                'plate' => 'ABC12D',
                'brand' => 'YAMAHA',
            ],
            'uploads' => [
                'cedula_front' => [
                    'path' => $path,
                    'filename' => 'cedula-frente.jpg',
                    'mime' => 'image/jpeg',
                ],
            ],
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/inscripciones/{$application->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inscriptions/Show')
                ->where('canEdit', false)
                ->where('form.first_name', 'Luigie')
                ->where('form.document_number', '1047475332')
                ->where('form.phone', '3001112233')
                ->where('form.license_document_number', '1047475332')
                ->where('form.license_expiration', '2028-03-15')
                ->where('form.license_categories.0.category', 'A2')
                ->where('form.health.blood_type', 'O')
                ->where('form.motorcycle.plate', 'ABC12D')
                ->where('documents.0.slug', 'cedula')
                ->where('documents.0.files.0.filename', 'cedula-frente.jpg')
                ->where('documents.0.files.0.side', 'front')
                ->where('documents.0.files.0.label', 'Cara'));

        $this->actingAs($director)
            ->get("/app/{$club->slug}/inscripciones/{$application->id}/archivos/cedula_front")
            ->assertOk();
    }

    public function test_a_copilot_only_uploads_cedula_and_can_submit_without_the_full_form(): void
    {
        Storage::fake('scans');
        Storage::fake('documents');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-copiloto',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '9988776655',
            'first_name' => 'Marta',
            'last_name' => 'Ruiz',
            'vehicle_role' => 'passenger',
        ])->assertRedirect("/i/{$link->token}");

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_front',
            'file' => $this->officialImage('cedula_front'),
        ])->assertRedirect("/i/{$link->token}");

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_back',
            'file' => $this->officialImage('cedula_back'),
        ])->assertRedirect("/i/{$link->token}");

        $this->post("/i/{$link->token}/enviar")->assertRedirect("/i/{$link->token}/gracias");

        $this->assertDatabaseHas('people', [
            'document_number' => '9988776655',
            'first_name' => 'Marta',
        ]);
    }

    public function test_a_copilot_filling_the_full_form_must_choose_a_registered_pilot(): void
    {
        Storage::fake('scans');
        Storage::fake('documents');

        [$club, $director] = $this->clubWithDirector();
        $pilot = Person::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Díaz']);
        $pilot->memberships()->create([
            'organization_id' => $club->id,
            'vehicle_role' => 'driver',
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
        ]);

        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-copiloto-form',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '5566778899',
            'first_name' => 'Elena',
            'last_name' => 'Soto',
            'vehicle_role' => 'passenger',
        ]);

        foreach (['cedula_front', 'cedula_back'] as $step) {
            $this->post("/i/{$link->token}/documentos", [
                'step' => $step,
                'file' => $this->officialImage($step),
            ]);
        }

        $this->post("/i/{$link->token}/continuar")->assertRedirect("/i/{$link->token}/formulario");

        $this->from("/i/{$link->token}/formulario")
            ->post("/i/{$link->token}/formulario", [
                'first_name' => 'Elena',
                'last_name' => 'Soto',
                'document_type' => 'cc',
                'document_number' => '5566778899',
                'vehicle_role' => 'passenger',
            ])
            ->assertRedirect("/i/{$link->token}/formulario")
            ->assertSessionHasErrors('pilot_person_id');

        $this->post("/i/{$link->token}/formulario", [
            'first_name' => 'Elena',
            'last_name' => 'Soto',
            'document_type' => 'cc',
            'document_number' => '5566778899',
            'vehicle_role' => 'passenger',
            'pilot_person_id' => $pilot->id,
        ])->assertRedirect("/i/{$link->token}/gracias");
    }

    public function test_the_final_form_is_prefilled_from_extracted_documents(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-formulario-datos',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $extracted = [
            'first_name' => 'LUIGIE',
            'last_name' => 'CALO',
            'document_number' => '1047475332',
            'birth_date' => '1990-04-12',
            'blood_type' => 'O',
            'rh' => 'positivo',
            'height_cm' => 178,
            'gender' => 'masculino',
            'license_document_number' => '1047475332',
            'license_categories' => [['category' => 'A2', 'expires_at' => '2028-03-15']],
            'plate' => 'ABC12D',
            'brand' => 'YAMAHA',
            'line' => 'NMAX',
            'year' => 2023,
            'color' => 'NEGRO',
            'owner_name' => 'LUIGIE ANDRES CALO',
            'owner_document_number' => '1047475332',
        ];

        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::ReadyForForm,
            'extracted_data' => $extracted,
            'form_draft' => (new InscriptionFormMapper)->toForm($extracted),
            'uploads' => [],
        ]);

        $this->withSession(['inscription.'.$link->id => $application->id])
            ->get("/i/{$link->token}/formulario")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionForm')
                ->where('form.first_name', 'LUIGIE')
                ->where('form.document_number', '1047475332')
                ->where('form.health.height_cm', 178)
                ->where('form.health.blood_type', 'O')
                ->where('form.license_document_number', '1047475332')
                ->where('form.motorcycle.plate', 'ABC12D')
                ->where('form.motorcycle.line', 'NMAX')
                ->where('form.motorcycle.color', 'NEGRO')
                ->where('form.motorcycle.owner_document_number', '1047475332'));
    }

    public function test_the_form_can_be_opened_without_uploading_documents(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-sin-docs',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1122334455',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'vehicle_role' => 'driver',
        ]);

        $this->get("/i/{$link->token}/formulario")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionForm')
                ->where('form.first_name', 'Ana')
                ->where('form.document_number', '1122334455'));

        $this->post("/i/{$link->token}/continuar")->assertRedirect("/i/{$link->token}/formulario");
    }

    public function test_a_person_can_return_to_basics_to_review_registered_data(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-regresar',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'vehicle_role' => 'passenger',
        ])->assertRedirect("/i/{$link->token}");

        $this->get("/i/{$link->token}/datos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionBasics')
                ->where('from_later_step', true)
                ->where('form.first_name', 'Luigie')
                ->where('form.document_number', '1047475332'));

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'middle_name' => 'Andrés',
            'last_name' => 'Calo',
            'second_last_name' => 'Pérez',
            'vehicle_role' => 'passenger',
        ])->assertRedirect("/i/{$link->token}");

        $this->get("/i/{$link->token}/datos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('form.first_name', 'Luigie')
                ->where('form.middle_name', 'Andrés')
                ->where('form.second_last_name', 'Pérez'));
    }

    public function test_a_document_can_be_replaced_and_read_again(): void
    {
        Storage::fake('scans');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-reemplazo',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'vehicle_role' => 'passenger',
        ]);

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_front',
            'file' => $this->textPdf('frente.pdf', 'CEDULA DE CIUDADANIA NUIP 11111111 NOMBRES ANA APELLIDOS PEREZ'),
        ]);

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_front',
            'file' => $this->textPdf('frente-2.pdf', 'CEDULA DE CIUDADANIA NUIP 1047475332 NOMBRES LUIGIE APELLIDOS CALO'),
        ])->assertRedirect("/i/{$link->token}");

        $this->get("/i/{$link->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionUpload')
                ->where('steps.0.done', true));

        $application = $link->applications()->first();
        $this->assertSame('1047475332', $application?->extracted_data['document_number'] ?? null);
        $this->assertSame('LUIGIE', $application?->extracted_data['first_name'] ?? null);
    }

    public function test_front_and_back_extraction_is_compared_with_registered_data(): void
    {
        Storage::fake('scans');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-resumen',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'second_last_name' => 'Pérez',
            'vehicle_role' => 'passenger',
        ]);

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_front',
            'file' => $this->textPdf('frente.pdf', 'CEDULA DE CIUDADANIA NUIP 1047475332 NOMBRES LUIGIE APELLIDOS CALO PEREZ'),
        ])->assertRedirect("/i/{$link->token}");

        $this->get("/i/{$link->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionUpload')
                ->where('review.both_sides', false)
                ->where('review.matches_registered', null)
                ->where('review.fields.0.status', 'match'));

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_back',
            'file' => $this->textPdf('reverso.pdf', 'CEDULA DE CIUDADANIA NUIP 1047475332 SEXO M G.S. O+'),
        ])->assertRedirect("/i/{$link->token}");

        $this->get("/i/{$link->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionUpload')
                ->where('review.both_sides', true)
                ->where('review.matches_registered', true)
                ->where('review.fields.0.key', 'document_number')
                ->where('review.fields.0.status', 'match'));
    }

    public function test_an_applicant_can_keep_the_value_they_choose_when_the_reading_differs(): void
    {
        Storage::fake('scans');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-conservar',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'second_last_name' => 'Pérez',
            'vehicle_role' => 'passenger',
        ]);

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_front',
            'file' => $this->textPdf('frente.pdf', 'CEDULA DE CIUDADANIA NUIP 1047475332 NOMBRES ANA APELLIDOS CALO'),
        ]);

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_back',
            'file' => $this->textPdf('reverso.pdf', 'CEDULA DE CIUDADANIA NUIP 1047475332'),
        ]);

        $this->get("/i/{$link->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('review.fields.1.key', 'first_name')
                ->where('review.fields.1.status', 'mismatch')
                ->where('review.fields.1.choices.0.source', 'registered')
                ->where('review.fields.1.choices.1.source', 'front'));

        $this->post("/i/{$link->token}/documentos/conservar", [
            'step' => 'cedula_back',
            'key' => 'first_name',
            'source' => 'registered',
        ])->assertRedirect();

        $application = $link->applications()->first();
        $this->assertSame('Luigie', $application?->form_draft['first_name'] ?? null);
        $this->assertSame('Luigie', $application?->form_draft['registered']['first_name'] ?? null);
        $this->assertSame('registered', $application?->form_draft['kept']['first_name']['source'] ?? null);

        $this->post("/i/{$link->token}/documentos/conservar", [
            'step' => 'cedula_back',
            'key' => 'first_name',
            'source' => 'manual',
            'value' => 'Ana Maria',
        ])->assertRedirect();

        $application = $link->applications()->first();
        $this->assertSame('Ana Maria', $application?->form_draft['first_name'] ?? null);
        $this->assertSame('manual', $application?->form_draft['kept']['first_name']['source'] ?? null);

        $this->post("/i/{$link->token}/documentos/conservar", [
            'step' => 'cedula_back',
            'key' => 'first_name',
            'source' => 'manual',
            'value' => '   ',
        ])->assertSessionHasErrors('value');

        $this->get("/i/{$link->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('review.fields.1.status', 'kept')
                ->where('review.fields.1.kept_source', 'manual')
                ->where('review.fields.1.kept_value', 'Ana Maria'));
    }

    public function test_a_photo_with_client_ocr_text_is_parsed(): void
    {
        Storage::fake('scans');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-ocr-foto',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'vehicle_role' => 'passenger',
        ]);

        $this->post("/i/{$link->token}/documentos", [
            'step' => 'cedula_front',
            'file' => $this->officialImage('cedula_front'),
            'ocr_text' => 'CEDULA DE CIUDADANIA NUMERO 1.047.475.332 NOMBRES LUIGIE APELLIDOS CALO',
        ])->assertRedirect("/i/{$link->token}");

        $application = $link->applications()->first();
        $this->assertSame('1047475332', $application?->extracted_data['document_number'] ?? null);
        $this->assertSame('LUIGIE', $application?->extracted_data['first_name'] ?? null);
        $this->assertSame('CALO', $application?->extracted_data['last_name'] ?? null);
    }

    public function test_staff_can_edit_pending_inscription_data_and_documents(): void
    {
        Storage::fake('documents');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-editar',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $person = Person::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'document_number' => '1122334455',
            'email' => 'ana@amm.test',
            'phone' => '3001112233',
            'city' => 'Cartagena',
            'status' => PersonStatus::PendingReview,
        ]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'vehicle_role' => 'driver',
            'status' => MembershipStatus::Pending,
            'joined_at' => now()->toDateString(),
        ]);

        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'person_id' => $person->id,
            'status' => InscriptionApplicationStatus::Submitted,
            'email' => 'ana@amm.test',
            'form_draft' => [
                'first_name' => 'Ana',
                'last_name' => 'Pérez',
                'document_number' => '1122334455',
                'vehicle_role' => 'driver',
            ],
        ]);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/inscripciones/{$application->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inscriptions/Show')
                ->where('canEdit', true)
                ->where('form.first_name', 'Ana'));

        $this->actingAs($director)
            ->put("/app/{$club->slug}/inscripciones/{$application->id}", [
                'first_name' => 'Anita',
                'last_name' => 'Pérez',
                'document_type' => 'cc',
                'document_number' => '1122334455',
                'phone' => '3001112233',
                'email' => 'ana@amm.test',
                'city' => 'Cartagena',
                'vehicle_role' => 'driver',
            ])
            ->assertRedirect();

        $this->assertSame('Anita', $person->fresh()->first_name);

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/documentos", [
                'type' => 'cedula',
                'files' => [UploadedFile::fake()->image('cedula.jpg')],
            ])
            ->assertRedirect();

        $this->assertSame(1, $person->documents()->count());

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/verificar")
            ->assertRedirect();

        $this->actingAs($director)
            ->put("/app/{$club->slug}/inscripciones/{$application->id}", [
                'first_name' => 'Otra',
                'last_name' => 'Pérez',
                'document_type' => 'cc',
                'document_number' => '1122334455',
                'phone' => '3001112233',
                'email' => 'ana@amm.test',
                'city' => 'Cartagena',
                'vehicle_role' => 'driver',
            ])
            ->assertForbidden();

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/documentos", [
                'type' => 'cedula',
                'files' => [UploadedFile::fake()->image('otra.jpg')],
            ])
            ->assertForbidden();
    }

    public function test_an_applicant_can_lookup_and_update_a_pending_request(): void
    {
        Storage::fake('documents');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-consulta',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $person = Person::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'document_number' => '1122334455',
            'email' => 'ana@amm.test',
            'phone' => '3001112233',
            'city' => 'Cartagena',
            'status' => PersonStatus::PendingReview,
        ]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'vehicle_role' => 'driver',
            'status' => MembershipStatus::Pending,
            'joined_at' => now()->toDateString(),
        ]);

        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'person_id' => $person->id,
            'status' => InscriptionApplicationStatus::Submitted,
            'email' => 'ana@amm.test',
            'form_draft' => [
                'first_name' => 'Ana',
                'last_name' => 'Pérez',
                'document_number' => '1122334455',
                'vehicle_role' => 'driver',
            ],
            'reference' => 'AMM-2026-0007',
        ]);

        $this->from("/i/{$link->token}")
            ->post("/i/{$link->token}/consultar", [
                'document_number' => '1.122.334.455',
            ])
            ->assertRedirect("/i/{$link->token}/gracias");

        $this->get("/i/{$link->token}/gracias")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionThanks')
                ->where('status', 'submitted')
                ->where('reference', 'AMM-2026-0007'));

        $this->get("/i/{$link->token}/solicitud")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/InscriptionRequest')
                ->where('can_edit', true)
                ->where('reference', 'AMM-2026-0007'));

        $this->put("/i/{$link->token}/solicitud", [
            'first_name' => 'Anita',
            'last_name' => 'Pérez',
            'document_type' => 'cc',
            'document_number' => '1122334455',
            'phone' => '3009998877',
            'email' => 'ana@amm.test',
            'city' => 'Cartagena',
            'vehicle_role' => 'driver',
        ])->assertRedirect();

        $this->assertSame('Anita', $person->fresh()->first_name);
        $this->assertSame('3009998877', $person->fresh()->phone);

        $this->post("/i/{$link->token}/solicitud/documentos", [
            'type' => 'cedula',
            'files' => [UploadedFile::fake()->image('cedula.jpg')],
        ])->assertRedirect();

        $this->assertSame(1, $person->documents()->count());

        $application->forceFill(['status' => InscriptionApplicationStatus::Verified])->save();

        $this->post("/i/{$link->token}/consultar", [
            'document_number' => '1122334455',
        ])->assertRedirect("/i/{$link->token}/gracias");

        $this->get("/i/{$link->token}/gracias")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('status', 'verified'));

        $this->from("/i/{$link->token}/gracias")
            ->post("/i/{$link->token}/consultar", [
                'document_number' => '0000000000',
            ])
            ->assertSessionHasErrors('document_number');

        $this->post("/i/{$link->token}/nueva")
            ->assertRedirect("/i/{$link->token}");

        $this->get("/i/{$link->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/InscriptionBasics'));

        $this->assertSame(
            InscriptionApplicationStatus::Verified,
            $application->fresh()->status,
        );
    }

    public function test_a_black_and_white_document_photo_is_accepted(): void
    {
        Storage::fake('scans');

        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-bn',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'vehicle_role' => 'driver',
        ]);

        $this->from("/i/{$link->token}")
            ->post("/i/{$link->token}/documentos", [
                'step' => 'cedula_front',
                'file' => $this->coloredUpload('cedula-bn.jpg', 180, 180, 180),
            ])
            ->assertRedirect("/i/{$link->token}")
            ->assertSessionDoesntHaveErrors('file');
    }

    public function test_approving_an_inscription_activates_the_member(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $person = Person::factory()->create(['status' => PersonStatus::PendingReview]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Pending,
            'joined_at' => now()->toDateString(),
        ]);
        $person->motorcycles()->create([
            'plate' => 'ABC12D',
            'brand' => 'Honda',
            'model' => 'XR',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'aprobar-integrante',
            'is_active' => true,
        ]);
        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'person_id' => $person->id,
            'status' => InscriptionApplicationStatus::Submitted,
            'email' => $person->email,
            'submitted_at' => now(),
        ]);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('slug', 'licencia')->firstOrFail()->id,
            'person_id' => $person->id,
            'status' => DocumentStatus::PendingReview,
        ]);

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/aprobar")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PersonStatus::Active, $person->fresh()->status);
        $this->assertSame(MembershipStatus::Active, $person->memberships()->firstOrFail()->status);
        $this->assertSame(InscriptionApplicationStatus::Verified, $application->fresh()->status);
        $this->assertSame(DocumentStatus::Valid, $document->fresh()->status);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/inscripciones/{$application->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inscriptions/Show')
                ->where('memberActive', true)
                ->where('canUpdateProfile', true)
                ->where('canUpdateMotorcycle', true));
    }

    public function test_staff_can_approve_a_draft_inscription_from_the_ficha(): void
    {
        [$club, $director] = $this->clubWithDirector();
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'aprobar-borrador',
            'is_active' => true,
        ]);
        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $club->id,
            'status' => InscriptionApplicationStatus::Draft,
            'document_number' => '1143354429',
            'form_draft' => [
                'first_name' => 'Luis',
                'last_name' => 'Castro',
                'document_type' => 'cc',
                'document_number' => '1143354429',
                'vehicle_role' => 'driver',
                'health' => [
                    'eps' => '',
                    'blood_type' => 'A',
                    'rh' => 'negativo',
                    'weight_kg' => '',
                    'height_cm' => '',
                ],
                'motorcycle' => [
                    'plate' => 'AAQ57G',
                    'brand' => '',
                    'year' => '',
                    'engine_displacement' => '',
                ],
            ],
        ]);

        $this->actingAs($director)
            ->post("/app/{$club->slug}/inscripciones/{$application->id}/aprobar")
            ->assertRedirect()
            ->assertSessionHas('success');

        $application->refresh();

        $this->assertNotNull($application->person_id);
        $this->assertSame(InscriptionApplicationStatus::Verified, $application->status);
        $this->assertSame(PersonStatus::Active, $application->person?->status);
        $this->assertSame(MembershipStatus::Active, $application->person?->memberships()->firstOrFail()->status);
    }

    public function test_an_existing_document_cannot_be_used_to_save_an_inscription(): void
    {
        [$club, $director] = $this->clubWithDirector();
        Person::factory()->create([
            'document_type' => 'cc',
            'document_number' => '1143354429',
            'first_name' => 'Luis',
            'last_name' => 'Castro',
        ]);
        $link = InscriptionLink::query()->create([
            'organization_id' => $club->id,
            'created_by' => $director->id,
            'token' => 'enlace-documento-existente',
            'name' => 'Prueba',
            'is_active' => true,
        ]);

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '1.143.354.429',
            'first_name' => 'Otro',
            'last_name' => 'Nombre',
            'vehicle_role' => 'driver',
        ])->assertSessionHasErrors([
            'document_number' => InscriptionLinkService::DOCUMENT_ALREADY_REGISTERED,
        ]);

        $this->assertSame(0, $link->applications()->count());

        $this->post("/i/{$link->token}/datos", [
            'document_type' => 'cc',
            'document_number' => '900100200',
            'first_name' => 'Otro',
            'last_name' => 'Nombre',
            'vehicle_role' => 'driver',
        ])->assertRedirect("/i/{$link->token}");

        $this->post("/i/{$link->token}/formulario", [
            'first_name' => 'Otro',
            'last_name' => 'Nombre',
            'document_type' => 'cc',
            'document_number' => '1143354429',
            'phone' => '3001112233',
            'email' => 'otro@amm.test',
            'city' => 'Cartagena',
            'motorcycle' => ['plate' => 'XYZ99A', 'brand' => 'Hero', 'vehicle_type' => 'motorcycle'],
        ])->assertSessionHasErrors([
            'document_number' => InscriptionLinkService::DOCUMENT_ALREADY_REGISTERED,
        ]);

        $this->post("/i/{$link->token}/documentos/conservar", [
            'step' => 'cedula_front',
            'key' => 'document_number',
            'source' => 'manual',
            'value' => '1143354429',
        ])->assertSessionHasErrors([
            'document_number' => InscriptionLinkService::DOCUMENT_ALREADY_REGISTERED,
        ]);

        $application = $link->applications()->first();
        $this->assertSame('900100200', $application?->document_number);
        $this->assertNull($link->applications()->where('status', InscriptionApplicationStatus::Submitted)->first());
        $this->assertSame(1, Person::query()->where('document_number', '1143354429')->count());
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function clubWithDirector(): array
    {
        $this->seed(DocumentTypeSeeder::class);

        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();

        $club = Organization::factory()->create(['slug' => 'amm-test']);
        $permissions->createOrganizationRoles($club);

        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        $permissions->assignRole($club, $director, $role);

        return [$club, $director];
    }

    private function officialImage(string $step): UploadedFile
    {
        $type = InscriptionDocumentSteps::find($step)['type'] ?? null;

        return match ($type) {
            'cedula' => $this->coloredUpload($step.'.jpg', 244, 211, 94),
            'licencia' => $this->coloredUpload($step.'.jpg', 244, 166, 200),
            'tarjeta-propiedad' => $this->coloredUpload($step.'.jpg', 134, 239, 172),
            'soat' => $this->coloredUpload($step.'.jpg', 96, 165, 250),
            'tecnomecanica' => $this->coloredUpload($step.'.jpg', 251, 146, 60),
            default => $this->coloredUpload($step.'.jpg', 180, 180, 180),
        };
    }

    private function textPdf(string $name, string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'amm').'.pdf';
        file_put_contents($path, "%PDF-1.4\n({$text})\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function coloredUpload(string $name, int $r, int $g, int $b): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'amm').'.jpg';
        $image = imagecreatetruecolor(320, 200);
        imagefilledrectangle($image, 0, 0, 319, 199, imagecolorallocate($image, $r, $g, $b));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }
}
