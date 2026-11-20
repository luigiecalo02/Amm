<?php

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Events\MemberAdded;
use App\Events\PersonCreated;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePersonAction
{
    public function execute(Organization $organization, array $data, ?User $actor = null): Person
    {
        return DB::transaction(function () use ($organization, $data, $actor): Person {
            $person = Person::query()->create([
                ...collect($data)->only([
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
                ])->map(fn ($value) => $this->blankToNull($value))->all(),
                'photo' => $data['photo'] ?? null,
                'status' => $data['status'] ?? PersonStatus::Active,
            ]);

            $health = $this->healthPayload($data['health'] ?? null);

            if ($health !== null) {
                $person->healthProfile()->create($health);
            }

            if (filled($data['emergency_contact']['name'] ?? null)) {
                $person->emergencyContacts()->create([
                    ...collect($data['emergency_contact'])->map(fn ($value) => $this->blankToNull($value))->all(),
                    'is_primary' => true,
                ]);
            }

            $member = $person->memberships()->create([
                'organization_id' => $organization->id,
                'membership_number' => $data['membership_number'] ?? null,
                'joined_at' => $data['joined_at'] ?? now()->toDateString(),
                'status' => $data['membership_status'] ?? MembershipStatus::Active,
                'vehicle_role' => $data['vehicle_role'] ?? 'driver',
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $motorcycle = $data['motorcycle'] ?? [];
            if (filled($motorcycle['plate'] ?? null) || filled($motorcycle['brand'] ?? null) || filled($motorcycle['line'] ?? null) || filled($motorcycle['model'] ?? null)) {
                $person->motorcycles()->create([
                    'plate' => filled($motorcycle['plate'] ?? null) ? $motorcycle['plate'] : 'PENDIENTE',
                    'brand' => filled($motorcycle['brand'] ?? null) ? $motorcycle['brand'] : 'Sin marca',
                    'model' => $this->blankToNull($motorcycle['line'] ?? $motorcycle['model'] ?? null),
                    'year' => $this->nullableInteger($motorcycle['year'] ?? null),
                    'color' => $this->blankToNull($motorcycle['color'] ?? null),
                    'engine_displacement' => $this->nullableInteger($motorcycle['engine_displacement'] ?? null),
                    'vehicle_type' => filled($motorcycle['vehicle_type'] ?? null) ? $motorcycle['vehicle_type'] : 'motorcycle',
                    'status' => 'active',
                ]);
            }

            PersonCreated::dispatch($person, $organization, $actor);
            MemberAdded::dispatch($member, $organization, $actor);

            return $person->load(['healthProfile', 'emergencyContacts', 'memberships']);
        });
    }

    /**
     * @param  array<string, mixed>|null  $health
     * @return array<string, mixed>|null
     */
    private function healthPayload(?array $health): ?array
    {
        if ($health === null) {
            return null;
        }

        $payload = [
            'eps' => $this->blankToNull($health['eps'] ?? null),
            'blood_type' => $this->blankToNull($health['blood_type'] ?? null),
            'rh' => $this->blankToNull($health['rh'] ?? null),
            'weight_kg' => $this->nullableNumber($health['weight_kg'] ?? null),
            'height_cm' => $this->nullableNumber($health['height_cm'] ?? null),
            'special_treatment' => $this->blankToNull($health['special_treatment'] ?? null),
            'medications' => $this->blankToNull($health['medications'] ?? null),
            'allergies' => $this->blankToNull($health['allergies'] ?? null),
        ];

        return collect($payload)->contains(fn ($value) => filled($value)) ? $payload : null;
    }

    private function blankToNull(mixed $value): mixed
    {
        return filled($value) ? $value : null;
    }

    private function nullableNumber(mixed $value): mixed
    {
        return is_numeric($value) ? $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
