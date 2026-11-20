<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\People\MemberPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UpdateMemberPhotosAction
{
    public function __construct(private readonly MemberPhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        Person $person,
        Organization $organization,
        User $actor,
        array $data,
        ?UploadedFile $photo,
        ?UploadedFile $motorcyclePhoto,
    ): void {
        if (! $person->isActiveIn($organization->id)) {
            throw ValidationException::withMessages([
                'status' => 'Aprueba la solicitud para activar al integrante antes de actualizar las fotos.',
            ]);
        }

        if ($photo) {
            $this->photos->replace($person, $photo, "photos/people/{$person->id}");
        }

        if (! $motorcyclePhoto) {
            return;
        }

        $motorcycle = $person->motorcycles()->whereKey($data['motorcycle_id'] ?? null)->first();

        if ($motorcycle === null) {
            throw ValidationException::withMessages([
                'motorcycle_id' => 'Elige la moto del integrante.',
            ]);
        }

        if (! $actor->can('update', [$motorcycle, $organization])) {
            throw ValidationException::withMessages([
                'motorcycle_photo' => 'No puedes actualizar la foto de la moto.',
            ]);
        }

        $this->photos->replace($motorcycle, $motorcyclePhoto, "photos/motorcycles/{$motorcycle->id}");
    }
}
