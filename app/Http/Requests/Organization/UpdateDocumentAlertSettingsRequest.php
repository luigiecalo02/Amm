<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentAlertSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('organization')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'licencia' => ['required', 'integer', 'min:1', 'max:365'],
            'soat' => ['required', 'integer', 'min:1', 'max:365'],
            'tecnomecanica' => ['required', 'integer', 'min:1', 'max:365'],
            'repeat_every' => ['nullable', 'integer', 'min:1', 'max:30'],
            'alert_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
        ];
    }
}
