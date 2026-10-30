<?php

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterMemberAction
{
    public function __construct(
        private readonly CreatePersonAction $createPerson,
        private readonly AssignOrganizationRoleAction $assignRole,
    ) {
    }

    public function execute(User $user, Organization $organization, array $data): Person
    {
        return DB::transaction(function () use ($user, $organization, $data): Person {
            $person = Person::query()
                ->where('document_type', $data['document_type'])
                ->where('document_number', $data['document_number'])
                ->first();

            if ($person && $person->users()->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages([
                    'document_number' => 'Este documento ya está vinculado a otra cuenta.',
                ]);
            }

            if (! $person) {
                $data['email'] = $user->email;
                $person = $this->createPerson->execute($organization, $data, $user);
            } else {
                $this->ensureMembership($person, $organization, $data);
            }

            $user->forceFill([
                'person_id' => $person->id,
                'name' => trim($person->first_name.' '.$person->last_name),
                'current_organization_id' => $organization->id,
            ])->save();

            $memberRole = Role::query()
                ->forOrganization($organization->id)
                ->where('slug', 'miembro')
                ->firstOrFail();

            $this->assignRole->execute($organization, $user, $memberRole, $user);

            return $person->fresh(['memberships', 'motorcycles']);
        });
    }

    private function ensureMembership(Person $person, Organization $organization, array $data): void
    {
        $person->memberships()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'person_id' => $person->id,
            ],
            [
                'joined_at' => now()->toDateString(),
                'status' => MembershipStatus::Active,
                'vehicle_role' => $data['vehicle_role'] ?? 'driver',
                'metadata' => $data['metadata'] ?? [],
            ]
        );

        if (! empty($data['motorcycle']['plate']) && ! $person->motorcycles()->where('plate', $data['motorcycle']['plate'])->exists()) {
            $person->motorcycles()->create([
                'plate' => $data['motorcycle']['plate'],
                'brand' => $data['motorcycle']['brand'] ?? 'Sin marca',
                'model' => $data['motorcycle']['model'] ?? null,
                'engine_displacement' => $data['motorcycle']['engine_displacement'] ?? null,
                'vehicle_type' => $data['motorcycle']['vehicle_type'] ?? 'motorcycle',
                'status' => 'active',
            ]);
        }
    }
}
