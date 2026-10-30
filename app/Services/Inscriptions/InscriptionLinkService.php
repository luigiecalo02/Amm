<?php

namespace App\Services\Inscriptions;

use App\Enums\InscriptionApplicationStatus;
use App\Models\InscriptionApplication;
use App\Models\InscriptionLink;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InscriptionLinkService
{
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

    public function normalizeReference(string $reference): string
    {
        $compact = strtoupper((string) preg_replace('/\s+/', '', $reference));

        if (preg_match('/^AMM-?(\d{4})-?(\d{1,4})$/', $compact, $match)) {
            return sprintf('AMM-%s-%04d', $match[1], (int) $match[2]);
        }

        return $compact;
    }

    public function lookup(InscriptionLink $link, string $documentNumber, string $reference): ?InscriptionApplication
    {
        $digits = preg_replace('/\D+/', '', $documentNumber) ?? '';
        $normalized = $this->normalizeReference($reference);

        if ($digits === '' || $normalized === '') {
            return null;
        }

        return InscriptionApplication::query()
            ->where('organization_id', $link->organization_id)
            ->where('reference', $normalized)
            ->whereHas('person', function ($query) use ($digits): void {
                $query->whereRaw(
                    "REPLACE(REPLACE(REPLACE(document_number, '.', ''), ' ', ''), '-', '') = ?",
                    [$digits]
                );
            })
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
     * @return array<int, array{id: string, name: string, document_number: string|null}>
     */
    public function pilots(Organization $organization): array
    {
        return OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->whereIn('vehicle_role', ['driver', 'both'])
            ->whereHas('person')
            ->with('person')
            ->get()
            ->map(fn (OrganizationMember $member) => [
                'id' => $member->person_id,
                'name' => $member->person?->full_name ?? 'Piloto',
                'document_number' => $member->person?->document_number,
            ])
            ->unique('id')
            ->values()
            ->all();
    }
}
