<?php

namespace App\Http\Requests\Person;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', [$this->route('person'), $this->route('organization')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = (new StorePersonRequest)->rules();
        $person = $this->route('person');

        $rules['document_number'] = [
            'required',
            'string',
            'max:40',
            Rule::unique('people', 'document_number')->ignore($person instanceof Person ? $person->id : null),
        ];

        return $rules;
    }
}
