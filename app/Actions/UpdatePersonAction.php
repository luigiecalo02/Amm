<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class UpdatePersonAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Person $person, Organization $organization, array $data): Person
    {
        return DB::transaction(function () use ($person, $organization, $data): Person {
            $person->fill(collect($data)->only([
                'document_type',
                'document_number',
                'document_issued_in',
                'first_name',
                'middle_name',
                'last_name',
                'second_last_name',
                'birth_date',
                'birth_city',
                'birth_department',
                'gender',
                'marital_status',
                'phone',
                'email',
                'address',
                'neighborhood',
                'city',
                'department',
            ])->all())->save();

            if (array_key_exists('health', $data)) {
                $person->healthProfile()->updateOrCreate(
                    ['person_id' => $person->id],
                    $data['health'] ?? []
                );
            }

            if (! empty($data['emergency_contact']['name'])) {
                $person->emergencyContacts()->updateOrCreate(
                    ['person_id' => $person->id, 'is_primary' => true],
                    $data['emergency_contact']
                );
            }

            $membership = $person->memberships()
                ->where('organization_id', $organization->id)
                ->first();

            if ($membership) {
                $membership->forceFill([
                    'vehicle_role' => $data['vehicle_role'] ?? $membership->vehicle_role,
                    'metadata' => [
                        ...($membership->metadata ?? []),
                        ...($data['metadata'] ?? []),
                        'license_document_number' => $data['license_document_number'] ?? ($membership->metadata['license_document_number'] ?? null),
                        'license_categories' => $data['license_categories'] ?? ($membership->metadata['license_categories'] ?? []),
                        'vehicle_owner_name' => $data['motorcycle']['owner_name'] ?? ($membership->metadata['vehicle_owner_name'] ?? null),
                        'vehicle_owner_document' => $data['motorcycle']['owner_document_number'] ?? ($membership->metadata['vehicle_owner_document'] ?? null),
                        'pilot_person_id' => $data['pilot_person_id'] ?? ($membership->metadata['pilot_person_id'] ?? null),
                    ],
                ])->save();
            }

            $motorcycle = $data['motorcycle'] ?? [];

            if (filled($motorcycle['plate'] ?? null)) {
                $payload = [
                    'plate' => $motorcycle['plate'],
                    'brand' => $motorcycle['brand'] ?? 'Sin marca',
                    'model' => $motorcycle['line'] ?? $motorcycle['model'] ?? null,
                    'year' => $motorcycle['year'] ?? null,
                    'color' => $motorcycle['color'] ?? null,
                    'engine_displacement' => $motorcycle['engine_displacement'] ?? null,
                    'vehicle_type' => $motorcycle['vehicle_type'] ?? 'motorcycle',
                    'status' => 'active',
                ];

                $existing = $person->motorcycles()->first();

                if ($existing) {
                    $existing->fill($payload)->save();
                } else {
                    $person->motorcycles()->create($payload);
                }
            }

            return $person->refresh()->load(['healthProfile', 'emergencyContacts', 'memberships', 'motorcycles']);
        });
    }
}
