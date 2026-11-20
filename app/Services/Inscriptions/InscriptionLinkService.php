<?php

namespace App\Services\Inscriptions;

use App\Enums\InscriptionApplicationStatus;
use App\Models\InscriptionApplication;
use App\Models\InscriptionLink;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InscriptionLinkService
{
    public const DOCUMENT_ALREADY_REGISTERED = 'Ese documento ya está registrado. No se puede guardar otra inscripción.';

    public function create(Organization $organization, User $creator, array $data): InscriptionLink
    {
        $days = isset($data['expires_in_days']) ? (int) $data['expires_in_days'] : 30;

        return InscriptionLink::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'token' => Str::lower(Str::random(40)),
            'name' => $data['name'] ?? 'Inscripción '.$organization->name,
            'is_active' => true,
            'expires_at' => $days > 0 ? now()->addDays($days) : null,
        ]);
    }

    public function resolveApplication(InscriptionLink $link): InscriptionApplication
    {
        $sessionKey = $this->sessionKey($link);
        $applicationId = session($sessionKey);

        $application = $applicationId
            ? InscriptionApplication::query()->whereKey($applicationId)->where('inscription_link_id', $link->id)->first()
            : null;

        if ($application && $application->canEditForm()) {
            return $application;
        }

        if ($application && ! $application->canEditForm()) {
            return $application;
        }

        $application = InscriptionApplication::query()->create([
            'inscription_link_id' => $link->id,
            'organization_id' => $link->organization_id,
            'status' => InscriptionApplicationStatus::Draft,
            'extracted_data' => [],
            'uploads' => [],
        ]);

        session([$sessionKey => $application->id]);

        return $application;
    }

    public function normalizeDocumentNumber(string $documentNumber): string
    {
        return preg_replace('/\D+/', '', $documentNumber) ?? '';
    }

    public function registeredPerson(string $documentNumber): ?Person
    {
        $digits = $this->normalizeDocumentNumber($documentNumber);
        $raw = mb_strtoupper(trim($documentNumber));

        if ($digits === '' && $raw === '') {
            return null;
        }

        return Person::query()
            ->where(function ($query) use ($digits, $raw): void {
                if ($digits !== '') {
                    $query->whereRaw(
                        "REPLACE(REPLACE(REPLACE(document_number, '.', ''), ' ', ''), '-', '') = ?",
                        [$digits],
                    );
                }

                if ($raw !== '' && $raw !== $digits) {
                    $method = $digits === '' ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}('UPPER(TRIM(document_number)) = ?', [$raw]);
                }
            })
            ->first();
    }

    public function attachDocumentNumber(
        InscriptionLink $link,
        InscriptionApplication $current,
        string $documentNumber,
    ): InscriptionApplication {
        $digits = $this->normalizeDocumentNumber($documentNumber);

        if ($digits === '') {
            return $current;
        }

        return DB::transaction(function () use ($link, $current, $digits): InscriptionApplication {
            $existing = InscriptionApplication::query()
                ->where('organization_id', $current->organization_id)
                ->where('document_number', $digits)
                ->whereKeyNot($current->id)
                ->lockForUpdate()
                ->orderByRaw("CASE status WHEN 'access_granted' THEN 0 WHEN 'verified' THEN 1 WHEN 'submitted' THEN 2 ELSE 3 END")
                ->orderByDesc('updated_at')
                ->first();

            if ($existing) {
                if ($existing->canEditForm() && $current->canEditForm()) {
                    $existing->forceFill([
                        'uploads' => filled($existing->uploads) ? $existing->uploads : $current->uploads,
                        'extracted_data' => filled($existing->extracted_data) ? $existing->extracted_data : $current->extracted_data,
                        'form_draft' => array_merge($existing->form_draft ?? [], $current->form_draft ?? []),
                    ])->save();
                }

                if ($current->canEditForm() && $current->id !== $existing->id) {
                    $current->delete();
                }

                $this->rememberLookup($link, $existing);

                return $existing;
            }

            $current->forceFill(['document_number' => $digits])->save();
            $this->rememberLookup($link, $current);

            return $current;
        });
    }

    public function sessionKey(InscriptionLink $link): string
    {
        return 'inscription.'.$link->id;
    }

    public function lookupSessionKey(InscriptionLink $link): string
    {
        return 'inscription.lookup.'.$link->id;
    }

    public function assignReference(InscriptionApplication $application): string
    {
        if (filled($application->reference)) {
            return (string) $application->reference;
        }

        $year = now()->year;
        $prefix = 'AMM-'.$year.'-';

        return DB::transaction(function () use ($application, $prefix): string {
            $last = InscriptionApplication::query()
                ->where('organization_id', $application->organization_id)
                ->where('reference', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference')
                ->value('reference');

            $sequence = $last ? ((int) substr((string) $last, -4)) + 1 : 1;
            $reference = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $application->forceFill(['reference' => $reference])->save();

            return $reference;
        });
    }

    public function lookup(InscriptionLink $link, string $documentNumber): ?InscriptionApplication
    {
        $digits = preg_replace('/\D+/', '', $documentNumber) ?? '';

        if ($digits === '') {
            return null;
        }

        return InscriptionApplication::query()
            ->where('organization_id', $link->organization_id)
            ->where(function ($query) use ($digits): void {
                $query->where('document_number', $digits)
                    ->orWhereHas('person', function ($person) use ($digits): void {
                        $person->whereRaw(
                            "REPLACE(REPLACE(REPLACE(document_number, '.', ''), ' ', ''), '-', '') = ?",
                            [$digits]
                        );
                    });
            })
            ->latest()
            ->first();
    }

    public function rememberLookup(InscriptionLink $link, InscriptionApplication $application): void
    {
        session([
            $this->sessionKey($link) => $application->id,
            $this->lookupSessionKey($link) => $application->id,
        ]);
    }

    public function lookedUpApplication(InscriptionLink $link): ?InscriptionApplication
    {
        return $this->sessionApplication($link, $this->lookupSessionKey($link));
    }

    public function sessionApplication(InscriptionLink $link, ?string $sessionKey = null): ?InscriptionApplication
    {
        $applicationId = session($sessionKey ?? $this->sessionKey($link));

        if (! $applicationId) {
            return null;
        }

        return InscriptionApplication::query()
            ->whereKey($applicationId)
            ->where('organization_id', $link->organization_id)
            ->first();
    }

    /**
     * @return array<int, array{id: string, name: string, document_type: string|null, document_number: string|null, phone: string|null, email: string|null, plate: string|null, brand: string|null, line: string|null}>
     */
    public function pilots(Organization $organization): array
    {
        return OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->whereIn('vehicle_role', ['driver', 'both'])
            ->whereHas('person')
            ->with('person.motorcycles')
            ->get()
            ->map(function (OrganizationMember $member): array {
                $motorcycle = $member->person?->motorcycles->first();

                return [
                    'id' => $member->person_id,
                    'name' => $member->person?->full_name ?? 'Piloto',
                    'document_type' => $member->person?->document_type,
                    'document_number' => $member->person?->document_number,
                    'phone' => $member->person?->phone,
                    'email' => $member->person?->email,
                    'plate' => $motorcycle?->plate,
                    'brand' => $motorcycle?->brand,
                    'line' => $motorcycle?->model,
                ];
            })
            ->unique('id')
            ->values()
            ->all();
    }
}
