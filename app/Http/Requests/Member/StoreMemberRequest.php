<?php

namespace App\Http\Requests\Member;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [OrganizationMember::class, $this->route('organization')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'exists:people,id'],
            'membership_number' => ['nullable', 'string', 'max:40'],
            'joined_at' => ['nullable', 'date'],
            'vehicle_role' => ['nullable', 'in:driver,passenger,both'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
