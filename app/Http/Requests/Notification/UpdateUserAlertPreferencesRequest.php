<?php

namespace App\Http\Requests\Notification;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAlertPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && ($this->user()?->canAccessOrganization($organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'boolean'],
            'birthdays' => ['required', 'boolean'],
        ];
    }
}
