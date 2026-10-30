<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [User::class, $this->route('organization')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'person_id' => ['nullable', 'exists:people,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $existing = User::query()->where('email', $this->input('email'))->first();

            if ($existing?->isSuperAdmin()) {
                $validator->errors()->add(
                    'email',
                    'El superadministrador no se asigna a un club. Ya puede ver todas las organizaciones.'
                );
            }
        });
    }
}
