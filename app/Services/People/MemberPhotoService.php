<?php

namespace App\Services\People;

use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Inscriptions\InscriptionLinkService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberPhotoService
{
    public function __construct(
        private readonly InscriptionLinkService $links,
    ) {}

    public function replace(Model $model, UploadedFile $file, string $directory): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
        $path = trim($directory, '/').'/photo.'.$extension;
        $previous = $model->getAttribute('photo');

        if (is_string($previous) && $previous !== $path && str_starts_with($previous, 'photos/')) {
            Storage::disk('documents')->delete($previous);
        }

        Storage::disk('documents')->put($path, $file->get());
        $model->forceFill(['photo' => $path])->save();
    }

    public function response(?string $path, string $directory): StreamedResponse
    {
        $directory = trim($directory, '/').'/';
        abort_unless(
            is_string($path) && str_starts_with($path, $directory) && Storage::disk('documents')->exists($path),
            404
        );

        return Storage::disk('documents')->response($path);
    }

    public function portraitUrl(Person $person, string $route): ?string
    {
        return $this->url($person->photo, "photos/people/{$person->id}", $route);
    }

    public function present(Person $person, Organization $organization): void
    {
        $this->assignPhotos($person, $organization);

        if ($person->motorcycles->isNotEmpty()) {
            return;
        }

        $pilot = $this->pilotOf($person);

        if ($pilot === null) {
            return;
        }

        $this->assignPhotos($pilot, $organization);
        $person->setRelation('motorcycles', $pilot->motorcycles);
    }

    private function assignPhotos(Person $person, Organization $organization): void
    {
        $person->loadMissing('motorcycles');
        $person->setAttribute(
            'photo_url',
            $this->portraitUrl($person, route('people.photo', [$organization, $person]))
        );

        $metadata = $person->relationLoaded('memberships')
            ? ($person->memberships->first()?->metadata ?? [])
            : [];
        $owner = is_array($metadata) ? ($metadata['vehicle_owner_name'] ?? null) : null;

        $person->motorcycles->each(function (Motorcycle $motorcycle) use ($organization, $person, $owner): void {
            $motorcycle->setAttribute(
                'photo_url',
                $this->url(
                    $motorcycle->photo,
                    "photos/motorcycles/{$motorcycle->id}",
                    route('people.motorcycles.photo', [$organization, $person, $motorcycle])
                )
            );
            $motorcycle->setAttribute('owner_name', is_string($owner) && $owner !== '' ? $owner : null);
        });
    }

    /**
     * @return list<array{id: string, name: string, document_type: string|null, document_number: string|null, phone: string|null, email: string|null, plate: string|null, brand: string|null, line: string|null}>
     */
    public function pilotsPayload(Person $person): array
    {
        $pilot = $this->pilotOf($person);

        if ($pilot === null) {
            return [];
        }

        return [$this->pilotChoice($pilot)];
    }

    /**
     * @return list<array{id: string, name: string, document_type: string|null, document_number: string|null, phone: string|null, email: string|null, plate: string|null, brand: string|null, line: string|null}>
     */
    public function pilotChoices(Organization $organization, Person $person): array
    {
        $choices = $this->links->pilots($organization);
        $assigned = $this->pilotsPayload($person);

        foreach ($assigned as $pilot) {
            $known = collect($choices)->contains(fn (array $choice): bool => $choice['id'] === $pilot['id']);

            if (! $known) {
                array_unshift($choices, $pilot);
            }
        }

        return array_values(array_filter(
            $choices,
            fn (array $choice): bool => $choice['id'] !== $person->id,
        ));
    }

    /**
     * @return array{id: string, name: string, document_type: string|null, document_number: string|null, phone: string|null, email: string|null, plate: string|null, brand: string|null, line: string|null}
     */
    private function pilotChoice(Person $pilot): array
    {
        $motorcycle = $pilot->motorcycles->first();

        return [
            'id' => $pilot->id,
            'name' => $pilot->full_name,
            'document_type' => $pilot->document_type,
            'document_number' => $pilot->document_number,
            'phone' => $pilot->phone,
            'email' => $pilot->email,
            'plate' => $motorcycle?->plate,
            'brand' => $motorcycle?->brand,
            'line' => $motorcycle?->model,
        ];
    }

    private function pilotOf(Person $person): ?Person
    {
        $membership = $person->relationLoaded('memberships') ? $person->memberships->first() : null;

        if (($membership?->vehicle_role ?? null) !== 'passenger') {
            return null;
        }

        $pilotId = is_array($membership->metadata) ? ($membership->metadata['pilot_person_id'] ?? null) : null;

        if (! is_string($pilotId) || $pilotId === '' || $pilotId === $person->id) {
            return null;
        }

        return Person::query()->with('motorcycles')->find($pilotId);
    }

    private function url(?string $path, string $directory, string $route): ?string
    {
        $directory = trim($directory, '/').'/';

        if (! is_string($path) || ! str_starts_with($path, $directory)) {
            return null;
        }

        return $route;
    }
}
