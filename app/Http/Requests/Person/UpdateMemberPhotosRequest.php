<?php

namespace App\Http\Requests\Person;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMemberPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = $this->route('person');

        return $person instanceof Person
            && ($this->user()?->can('update', [$person, $this->route('organization')]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'max:5120'],
            'motorcycle_id' => ['nullable', 'string'],
            'motorcycle_photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasFile('photo') && ! $this->hasFile('motorcycle_photo')) {
                $validator->errors()->add('photo', 'Elige una foto de perfil o de la moto.');
            }

            if ($this->hasFile('motorcycle_photo') && ! $this->filled('motorcycle_id')) {
                $validator->errors()->add('motorcycle_id', 'Elige la moto del integrante.');
            }
        });
    }
}
