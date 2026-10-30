<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Enums\InscriptionApplicationStatus;
use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\InscriptionApplication;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GrantInscriptionAccessAction
{
    public function __construct(private readonly AssignOrganizationRoleAction $assign)
    {
    }

    /**
     * @return array{user: User, password: string}
     */
    public function execute(
        InscriptionApplication $application,
        Organization $organization,
        User $actor,
        array $data,
    ): array {
        if ($application->status !== InscriptionApplicationStatus::Verified) {
            throw ValidationException::withMessages([
                'status' => 'Primero verifica la documentación.',
            ]);
        }

        if (! $application->person_id) {
            throw ValidationException::withMessages([
                'person_id' => 'La inscripción no tiene persona asociada.',
            ]);
        }

        return DB::transaction(function () use ($application, $organization, $actor, $data): array {
            $email = $data['email'] ?? $application->email;
            $password = filled($data['password'] ?? null) ? (string) $data['password'] : Str::password(10);

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $application->person?->full_name ?: ($email ?? 'Miembro AMM'),
                    'password' => $password,
                    'person_id' => $application->person_id,
                    'current_organization_id' => $organization->id,
                ]
            );

            if (! $user->person_id) {
                $user->forceFill(['person_id' => $application->person_id])->save();
            }

            $role = Role::query()
                ->forOrganization($organization->id)
                ->whereKey($data['role_id'])
                ->firstOrFail();

            $this->assign->execute($organization, $user, $role, $actor);

            $application->person?->forceFill(['status' => PersonStatus::Active])->save();
            $application->person?->memberships()
                ->where('organization_id', $organization->id)
                ->update(['status' => MembershipStatus::Active]);

            $application->person?->documents()->update([
                'status' => DocumentStatus::Valid,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ]);

            $application->forceFill([
                'access_user_id' => $user->id,
                'email' => $email,
                'status' => InscriptionApplicationStatus::AccessGranted,
                'access_granted_at' => now(),
            ])->save();

            return ['user' => $user, 'password' => $password];
        });
    }
}
