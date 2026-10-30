<?php

namespace Database\Seeders;

use App\Actions\CreatePersonAction;
use App\Enums\DocumentStatus;
use App\Models\DocumentType;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use App\Services\Permissions\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoClubSeeder extends Seeder
{
    public function run(OrganizationService $organizations, CreatePersonAction $createPerson): void
    {
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'director@amm.test'],
            [
                'name' => 'Superadministrador AMM',
                'password' => Hash::make('password'),
                'is_platform_admin' => true,
            ]
        );

        $superAdmin->organizationRoles()->delete();

        $organization = Organization::query()->where('slug', 'amm-cartagena')->first()
            ?? $organizations->create([
                'name' => 'Club AMM Cartagena',
                'slug' => 'amm-cartagena',
                'type' => 'motorcycle_club',
                'description' => 'Asociación de motociclistas AMM Cartagena',
                'email' => 'cartagena@amm.test',
                'phone' => '3000000000',
                'country' => 'CO',
                'department' => 'Bolívar',
                'city' => 'Cartagena',
                'address' => 'Cartagena, Colombia',
            ], $superAdmin);

        $person = \App\Models\Person::query()->where('document_number', '1047475332')->first();

        if (! $person) {
            $person = $createPerson->execute($organization, [
            'document_type' => 'cc',
            'document_number' => '1047475332',
            'document_issued_in' => 'Cartagena',
            'first_name' => 'Luis Fernando',
            'last_name' => 'Romero',
            'second_last_name' => 'Ramos',
            'birth_date' => '1995-01-08',
            'birth_city' => 'Cartagena',
            'birth_department' => 'Bolívar',
            'gender' => 'masculino',
            'marital_status' => 'soltero',
            'phone' => '3104525895',
            'email' => 'lfrr83@gmail.com',
            'address' => 'Cll 4 Sector 1 de Mayo',
            'neighborhood' => 'Pozón',
            'city' => 'Cartagena',
            'department' => 'Bolívar',
            'membership_number' => 'AMM-001',
            'vehicle_role' => 'driver',
            'metadata' => [
                'church_name' => 'Galilea',
                'church_district' => 'Oriental',
                'baptized' => true,
            ],
            'health' => [
                'eps' => 'Nueva EPS',
                'blood_type' => 'O',
                'rh' => 'positivo',
                'weight_kg' => 71,
                'height_cm' => 175,
                'special_treatment' => 'Ninguno',
                'medications' => 'Ninguno',
                'allergies' => 'Ninguno',
            ],
            'emergency_contact' => [
                'name' => 'Rigoberta Ramos Camargo',
                'phone' => '3015241071',
                'address' => 'Pozón',
                'relationship' => 'Madre',
            ],
        ]);
        }

        $motorcycle = Motorcycle::query()->updateOrCreate(
            ['plate' => 'XZZ01G'],
            [
                'person_id' => $person->id,
                'brand' => 'Hero',
                'model' => 'Hunk FI',
                'year' => 2025,
                'engine_displacement' => 160,
                'vehicle_type' => 'motorcycle',
                'status' => 'active',
            ]
        );

        $cedula = DocumentType::query()->where('slug', 'cedula')->first();
        $licencia = DocumentType::query()->where('slug', 'licencia')->first();
        $soat = DocumentType::query()->where('slug', 'soat')->first();
        $tecno = DocumentType::query()->where('slug', 'tecnomecanica')->first();

        $person->documents()->firstOrCreate(
            ['document_type_id' => $cedula?->id, 'document_number' => '1047475332'],
            ['status' => DocumentStatus::Valid, 'issue_date' => '2013-01-08']
        );

        $person->documents()->firstOrCreate(
            ['document_type_id' => $licencia?->id, 'document_number' => '1047475332'],
            ['status' => DocumentStatus::Valid, 'expiration_date' => now()->addMonths(8)]
        );

        $motorcycle->documents()->firstOrCreate(
            ['document_type_id' => $soat?->id, 'document_number' => 'SOAT-XZZ01G'],
            ['status' => DocumentStatus::Valid, 'expiration_date' => now()->addDays(20)]
        );

        $motorcycle->documents()->firstOrCreate(
            ['document_type_id' => $tecno?->id, 'document_number' => 'TM-XZZ01G'],
            ['status' => DocumentStatus::Valid, 'expiration_date' => now()->addYear()]
        );

        $clubDirector = User::query()->updateOrCreate(
            ['email' => 'cartagena@amm.test'],
            [
                'name' => 'Director Cartagena',
                'password' => Hash::make('password'),
                'is_platform_admin' => false,
                'current_organization_id' => $organization->id,
            ]
        );

        app(PermissionService::class)->createOrganizationRoles($organization);

        Organization::query()->each(
            fn (Organization $club) => app(PermissionService::class)->createOrganizationRoles($club)
        );

        $superRole = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', Role::CLUB_SUPER_ADMIN_SLUG)
            ->first();

        if ($superRole) {
            $organization->userRoles()
                ->where('user_id', $clubDirector->id)
                ->where('role_id', '!=', $superRole->id)
                ->delete();

            $organization->userRoles()->firstOrCreate([
                'user_id' => $clubDirector->id,
                'role_id' => $superRole->id,
            ]);
        }

        $memberUser = User::query()->updateOrCreate(
            ['email' => 'luis@amm.test'],
            [
                'name' => 'Luis Fernando Romero',
                'password' => Hash::make('password'),
                'is_platform_admin' => false,
                'person_id' => $person->id,
                'current_organization_id' => $organization->id,
            ]
        );

        $memberRole = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', 'miembro')
            ->first();

        if ($memberRole) {
            $organization->userRoles()->firstOrCreate([
                'user_id' => $memberUser->id,
                'role_id' => $memberRole->id,
            ]);
        }
    }
}
