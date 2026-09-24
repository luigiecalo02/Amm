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
                ])->all(),
                'status' => PersonStatus::Active,
            ]);

            if (! empty($data['health'])) {
                $person->healthProfile()->create($data['health']);
            }

            if (! empty($data['emergency_contact']['name'])) {
                $person->emergencyContacts()->create([
                    ...$data['emergency_contact'],
                    'is_primary' => true,
                ]);
            }

            $member = $person->memberships()->create([
                'organization_id' => $organization->id,
                'membership_number' => $data['membership_number'] ?? null,
                'joined_at' => $data['joined_at'] ?? now()->toDateString(),
                'status' => MembershipStatus::Active,
                'vehicle_role' => $data['vehicle_role'] ?? 'driver',
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            PersonCreated::dispatch($person, $organization, $actor);
            MemberAdded::dispatch($member, $organization, $actor);

            return $person->load(['healthProfile', 'emergencyContacts', 'memberships']);
        });
    }
}
