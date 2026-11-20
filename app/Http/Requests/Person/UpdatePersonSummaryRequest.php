<?php

namespace App\Http\Requests\Person;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Person;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePersonSummaryRequest extends StorePersonRequest
{
    public function authorize(): bool
    {
        $person = $this->route('person');
        $organization = $this->route('organization');

        return $person instanceof Person
            && $organization instanceof Organization
            && $person->belongsToOrganization($organization->id)
            && ($this->user()?->hasPermission('people.summary.update', $organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $person = $this->route('person');

        $rules['document_number'] = [
            'required',
            'string',
            'max:40',
            Rule::unique('people', 'document_number')->ignore($person instanceof Person ? $person->id : null),
        ];
        $rules['pilot_person_id'] = ['nullable', 'string', 'required_if:vehicle_role,passenger'];

        foreach (array_keys($rules) as $key) {
            if ($key === 'motorcycle' || str_starts_with($key, 'motorcycle.')) {
                unset($rules[$key]);
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->isPassenger()) {
                return;
            }

            $pilotId = $this->input('pilot_person_id');
            $person = $this->route('person');
            $organization = $this->route('organization');

            if (! is_string($pilotId) || $pilotId === '' || ! $person instanceof Person || ! $organization instanceof Organization) {
                return;
            }

            if ($pilotId === $person->id) {
                $validator->errors()->add('pilot_person_id', 'El copiloto no puede ser su propio piloto.');

                return;
            }

            $exists = OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('person_id', $pilotId)
                ->whereIn('vehicle_role', ['driver', 'both'])
                ->exists();

            if (! $exists) {
                $validator->errors()->add('pilot_person_id', 'Elige un piloto ya inscrito en este club.');
            }
        });
    }

    private function isPassenger(): bool
    {
        return $this->input('vehicle_role') === 'passenger';
    }
}
