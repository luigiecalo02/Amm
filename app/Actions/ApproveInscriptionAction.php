<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Enums\InscriptionApplicationStatus;
use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\Document;
use App\Models\InscriptionApplication;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\Inscriptions\InscriptionFormMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveInscriptionAction
{
    public function __construct(
        private readonly SubmitPublicInscriptionAction $submit,
        private readonly InscriptionFormMapper $mapper,
    ) {}

    public function execute(InscriptionApplication $application, User $actor): void
    {
        if (! in_array($application->status, [
            InscriptionApplicationStatus::Draft,
            InscriptionApplicationStatus::ReadyForForm,
            InscriptionApplicationStatus::Submitted,
            InscriptionApplicationStatus::Verified,
            InscriptionApplicationStatus::AccessGranted,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Esta solicitud todavía no se puede aprobar.',
            ]);
        }

        DB::transaction(function () use ($application, $actor): void {
            $person = $application->person ?? $this->createPersonFromDraft($application);

            $motorcycleIds = $person->motorcycles()->pluck('id');

            Document::query()
                ->where(function ($query) use ($person, $motorcycleIds): void {
                    $query->where('person_id', $person->id);

                    if ($motorcycleIds->isNotEmpty()) {
                        $query->orWhereIn('motorcycle_id', $motorcycleIds);
                    }
                })
                ->update([
                    'status' => DocumentStatus::Valid->value,
                    'verified_at' => now(),
                    'verified_by' => $actor->id,
                ]);

            $person->forceFill(['status' => PersonStatus::Active])->save();
            $person->memberships()
                ->where('organization_id', $application->organization_id)
                ->update(['status' => MembershipStatus::Active->value]);

            if (in_array($application->status, [
                InscriptionApplicationStatus::Draft,
                InscriptionApplicationStatus::ReadyForForm,
                InscriptionApplicationStatus::Submitted,
            ], true)) {
                $application->forceFill([
                    'status' => InscriptionApplicationStatus::Verified,
                    'verified_at' => now(),
                    'verified_by' => $actor->id,
                ])->save();
            }
        });
    }

    private function createPersonFromDraft(InscriptionApplication $application): Person
    {
        $organization = $application->organization ?? Organization::query()->find($application->organization_id);

        if ($organization === null) {
            throw ValidationException::withMessages([
                'status' => 'La solicitud no tiene club asociado.',
            ]);
        }

        $data = $this->mapper->forApplication($application);
        $data['document_number'] = filled($data['document_number'] ?? null)
            ? $data['document_number']
            : $application->document_number;
        $data['email'] = filled($data['email'] ?? null) ? $data['email'] : $application->email;

        if (! filled($data['first_name'] ?? null) || ! filled($data['last_name'] ?? null) || ! filled($data['document_number'] ?? null)) {
            throw ValidationException::withMessages([
                'status' => 'Faltan nombre, apellido o cédula para aprobar esta solicitud.',
            ]);
        }

        return $this->submit->execute($application, $organization, $data);
    }
}
